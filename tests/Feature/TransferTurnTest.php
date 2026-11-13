<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

/*
 * Máy chủ trung chuyển (TURN của Metered) cho gửi file: credential cố định trong .env, chỉ trả qua
 * endpoint có giới hạn, giới hạn lượt theo IP và tắt khi hết hạn mức tháng.
 */

function turnRoom(): array
{
    return test()->postJson(route('transfer.rooms.store'))->assertCreated()->json();
}

function requestTurn(array $room, ?string $token = null)
{
    return test()->postJson(route('transfer.rooms.turn', $room['code']), [], ['X-Room-Token' => $token ?? $room['token']]);
}

function enableTurn(): void
{
    config([
        'services.transfer.turn_username' => 'metered-user',
        'services.transfer.turn_password' => 'metered-pass',
        'services.transfer.turn_daily_per_ip' => 2,
    ]);
}

/**
 * @param  array<string, mixed>  $usage
 */
function fakeUsage(array $usage): void
{
    config(['services.transfer.metered_app' => 'sotay', 'services.transfer.metered_secret_key' => 'secret-key']);
    Http::fake(['sotay.metered.live/api/v1/turn/current_usage*' => Http::response($usage)]);
}

test('TURN is off until the Metered username and password are configured', function () {
    $room = turnRoom();
    expect($room['turnAvailable'])->toBeFalse()->and($room['turnNotice'])->toBeNull();

    requestTurn($room)->assertStatus(503)->assertJson(['reason' => 'turn_disabled']);
});

test('the room response never carries TURN credentials, only the endpoint does', function () {
    enableTurn();
    $room = turnRoom();

    expect($room['turnAvailable'])->toBeTrue()
        ->and($room['maxRelayBytes'])->toBe(500 * 1024 ** 2)
        ->and(json_encode($room))->not->toContain('metered-pass')->not->toContain('relay.metered.ca');

    expect(requestTurn($room)->assertOk()->json('iceServers'))->toBe([
        ['urls' => ['stun:stun.cloudflare.com:3478', 'stun:stun.l.google.com:19302']],
        ['urls' => ['stun:stun.relay.metered.ca:80']],
        [
            'urls' => [
                'turn:global.relay.metered.ca:80',
                'turn:global.relay.metered.ca:80?transport=tcp',
                'turn:global.relay.metered.ca:443',
                'turns:global.relay.metered.ca:443?transport=tcp',
            ],
            'username' => 'metered-user',
            'credential' => 'metered-pass',
        ],
    ]);
});

test('both devices of a room get TURN but it counts as one session', function () {
    enableTurn();
    $room = turnRoom();
    $receiver = $this->postJson(route('transfer.rooms.join', $room['code']))->assertOk()->json();

    requestTurn($room)->assertOk();
    requestTurn($room, $receiver['token'])->assertOk();
    requestTurn(turnRoom())->assertOk();
    requestTurn(turnRoom())->assertStatus(429)->assertJson(['reason' => 'turn_daily_limit']);
});

test('each IP gets a limited number of relayed sessions per day', function () {
    enableTurn();

    requestTurn(turnRoom())->assertOk();
    requestTurn(turnRoom())->assertOk();
    requestTurn(turnRoom())->assertStatus(429)->assertJson(['reason' => 'turn_daily_limit']);

    $this->travel(25)->hours();
    requestTurn(turnRoom())->assertOk();
});

test('the TURN endpoint is rate limited per minute', function () {
    enableTurn();
    $room = turnRoom();

    foreach (range(1, 10) as $ignored) {
        requestTurn($room)->assertOk();
    }
    requestTurn($room)->assertStatus(429)->assertJson(['reason' => 'too_many_turn_requests']);
});

test('once the monthly quota is used up the page falls back to direct connections only', function () {
    enableTurn();
    fakeUsage(['usageInGB' => 19.6, 'quotaInGB' => 20, 'overageInGB' => 0]);

    $room = turnRoom();
    requestTurn($room)->assertStatus(429)->assertJson(['reason' => 'turn_monthly_limit']);

    $next = turnRoom();
    expect($next['turnAvailable'])->toBeFalse()
        ->and($next['turnNotice'])->toContain('hết dung lượng miễn phí của tháng');
});

test('TURN keeps working when the usage check is not configured or Metered is down', function () {
    enableTurn();
    requestTurn(turnRoom())->assertOk();

    config(['services.transfer.metered_app' => 'sotay', 'services.transfer.metered_secret_key' => 'secret-key']);
    Http::fake(['sotay.metered.live/*' => Http::response('oops', 500)]);
    requestTurn(turnRoom())->assertOk();
});

test('only devices in the room can ask for TURN', function () {
    enableTurn();
    $room = turnRoom();

    requestTurn($room, 'nope')->assertForbidden()->assertJsonMissing(['credential' => 'metered-pass']);
});
