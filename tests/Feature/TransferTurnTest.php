<?php

declare(strict_types=1);

use App\Actions\Transfer\TurnQuota;
use App\Models\TransferTurnCredential;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * Máy chủ trung chuyển (TURN Open Relay của Metered) cho gửi file: mật khẩu tạm thời tạo sẵn,
 * mỗi phòng một mật khẩu, giới hạn 500 MB mỗi lượt gửi và tắt khi hết hạn mức tháng.
 */

function turnRoom(): array
{
    return test()->postJson(route('transfer.rooms.store'))->assertCreated()->json();
}

function requestTurn(array $room, ?string $token = null)
{
    return test()->postJson(route('transfer.rooms.turn', $room['code']), [], ['X-Room-Token' => $token ?? $room['token']]);
}

/**
 * @param  array<string, mixed>  $usage
 */
function fakeMetered(array $usage = ['usageInGB' => 1.5, 'quotaInGB' => 20, 'overageInGB' => 0]): void
{
    config([
        'services.transfer.metered_app' => 'sotay',
        'services.transfer.metered_secret_key' => 'secret-key',
        'services.transfer.turn_daily_per_ip' => 2,
        'services.transfer.turn_pool_size' => 2,
    ]);

    $counter = 0;
    Http::fake([
        'sotay.metered.live/api/v1/turn/credential?*' => function () use (&$counter) {
            $counter++;

            return Http::response(['username' => "user-{$counter}", 'password' => "pass-{$counter}", 'expiryInSeconds' => 21600, 'label' => 'gui-file', 'apiKey' => "cred-key-{$counter}"]);
        },
        'sotay.metered.live/api/v1/turn/credentials?*' => fn (ClientRequest $request) => Http::response([
            ['urls' => 'stun:stun.relay.metered.ca:80'],
            ['urls' => 'turn:standard.relay.metered.ca:80', 'username' => 'u-'.$request['apiKey'], 'credential' => 'c'],
            ['urls' => 'turns:standard.relay.metered.ca:443?transport=tcp', 'username' => 'u-'.$request['apiKey'], 'credential' => 'c'],
        ]),
        'sotay.metered.live/api/v1/turn/current_usage*' => Http::response($usage),
    ]);
}

test('TURN is off until the Metered secret key is configured', function () {
    $room = turnRoom();
    expect($room['turnAvailable'])->toBeFalse()->and($room['turnNotice'])->toBeNull();

    requestTurn($room)->assertStatus(503)->assertJson(['reason' => 'turn_disabled']);
});

test('the scheduler keeps short-lived credentials ready ahead of time', function () {
    fakeMetered();

    $this->artisan('transfer:turn-pool')->assertSuccessful();

    expect(TransferTurnCredential::query()->available()->count())->toBe(2);
    $credential = TransferTurnCredential::query()->first();
    expect($credential->expires_at->diffInHours(now(), true))->toBeGreaterThan(5.9)
        ->and($credential->secondsUntilReady())->toBeGreaterThan(100);

    Http::assertSent(fn (ClientRequest $request) => str_starts_with($request->url(), 'https://sotay.metered.live/api/v1/turn/credential?')
        && str_contains($request->url(), 'secretKey=secret-key')
        && $request['expiryInSeconds'] === 21600);

    // Đã đủ kho thì không tạo thêm.
    $this->artisan('transfer:turn-pool')->assertSuccessful();
    expect(TransferTurnCredential::query()->count())->toBe(2);
});

test('each room gets its own ready credential, shared by both devices and counted once', function () {
    fakeMetered();
    $this->artisan('transfer:turn-pool');
    $this->travel(3)->minutes();

    $room = turnRoom();
    expect($room['turnAvailable'])->toBeTrue()->and($room['maxRelayBytes'])->toBe(500 * 1024 ** 2);
    $receiver = $this->postJson(route('transfer.rooms.join', $room['code']))->assertOk()->json();

    $first = requestTurn($room)->assertOk()->json();
    $second = requestTurn($room, $receiver['token'])->assertOk()->json();

    expect($first['readyIn'])->toBe(0)
        ->and($first['iceServers'])->toBe($second['iceServers'])
        ->and($first['iceServers'][1])->toBe(['urls' => ['stun:stun.relay.metered.ca:80']])
        ->and($first['iceServers'][2]['urls'])->toBe(['turn:standard.relay.metered.ca:80']);

    $other = requestTurn(turnRoom())->assertOk()->json();
    expect($other['iceServers'])->not->toBe($first['iceServers']);

    expect(TransferTurnCredential::query()->whereNotNull('issued_at')->count())->toBe(2);
});

test('with an empty pool a credential is made on the spot and the browser is told to wait', function () {
    fakeMetered();

    $response = requestTurn(turnRoom())->assertOk()->json();

    expect($response['readyIn'])->toBeGreaterThan(100)->and($response['iceServers'])->not->toBeEmpty();
});

test('a credential close to its hard expiry is not handed out', function () {
    fakeMetered();
    $this->artisan('transfer:turn-pool');
    $this->travel(5)->hours();

    $response = requestTurn(turnRoom())->assertOk()->json();

    expect($response['readyIn'])->toBeGreaterThan(100);
});

test('the secret key never reaches the browser', function () {
    fakeMetered();
    $room = turnRoom();

    expect(json_encode($room))->not->toContain('secret-key')
        ->and(requestTurn($room)->getContent())->not->toContain('secret-key');
});

test('each IP gets a limited number of relayed sessions per day', function () {
    fakeMetered();

    requestTurn(turnRoom())->assertOk();
    requestTurn(turnRoom())->assertOk();
    requestTurn(turnRoom())->assertStatus(429)->assertJson(['reason' => 'turn_daily_limit']);

    $this->travel(25)->hours();
    requestTurn(turnRoom())->assertOk();
});

test('once the monthly quota is used up the page falls back to direct connections only', function () {
    fakeMetered(['usageInGB' => 19.6, 'quotaInGB' => 20, 'overageInGB' => 0]);

    $this->artisan('transfer:turn-pool')->assertSuccessful();
    expect(TransferTurnCredential::query()->count())->toBe(0);

    $room = turnRoom();
    expect($room['turnAvailable'])->toBeFalse()
        ->and($room['turnNotice'])->toContain('hết dung lượng miễn phí của tháng');

    requestTurn($room)->assertStatus(429)->assertJson(['reason' => 'turn_monthly_limit']);
});

test('the quota is checked before handing out even when the scheduler has not run', function () {
    fakeMetered(['usageInGB' => 20, 'quotaInGB' => 20, 'overageInGB' => 0]);
    $room = turnRoom();
    expect($room['turnAvailable'])->toBeTrue();

    requestTurn($room)->assertStatus(429)->assertJson(['reason' => 'turn_monthly_limit']);
    expect(Cache::get(TurnQuota::cacheKey())['exhausted'])->toBeTrue();
});

test('a Metered outage gives a clear message and does not use up a session', function () {
    config([
        'services.transfer.metered_app' => 'sotay',
        'services.transfer.metered_secret_key' => 'secret-key',
        'services.transfer.turn_daily_per_ip' => 1,
    ]);
    $down = true;
    Http::fake(['sotay.metered.live/*' => function (ClientRequest $request) use (&$down) {
        return match (true) {
            $down => Http::response('oops', 500),
            str_contains($request->url(), '/credential?') => Http::response(['username' => 'u', 'password' => 'p', 'apiKey' => 'k']),
            str_contains($request->url(), '/credentials?') => Http::response([['urls' => 'turn:standard.relay.metered.ca:80', 'username' => 'u', 'credential' => 'p']]),
            default => Http::response(['usageInGB' => 0, 'quotaInGB' => 20]),
        };
    }]);

    requestTurn(turnRoom())->assertStatus(503)->assertJson(['reason' => 'turn_unavailable']);

    $down = false;
    requestTurn(turnRoom())->assertOk();
});

test('only devices in the room can ask for TURN', function () {
    fakeMetered();
    $room = turnRoom();

    requestTurn($room, 'nope')->assertForbidden();
    Http::assertNothingSent();
});

test('expired credentials are cleaned up', function () {
    fakeMetered();
    $this->artisan('transfer:turn-pool');
    $this->travel(7)->hours();

    $this->artisan('transfer:turn-pool');

    expect(TransferTurnCredential::query()->expired()->count())->toBe(0)
        ->and(TransferTurnCredential::query()->count())->toBe(2);
});
