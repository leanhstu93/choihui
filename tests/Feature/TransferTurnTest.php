<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;

/*
 * Máy chủ trung chuyển (Cloudflare TURN) cho gửi file: chỉ cấp khi nối thẳng thất bại, có giới hạn.
 */

function turnRoom(): array
{
    return test()->postJson(route('transfer.rooms.store'))->assertCreated()->json();
}

function requestTurn(array $room)
{
    return test()->postJson(route('transfer.rooms.turn', $room['code']), [], ['X-Room-Token' => $room['token']]);
}

function enableTurn(): void
{
    config([
        'services.transfer.turn_key_id' => 'key-123',
        'services.transfer.turn_api_token' => 'secret-token',
        'services.transfer.turn_daily_per_ip' => 2,
        'services.transfer.turn_monthly_sessions' => 3,
    ]);

    Http::fake(['rtc.live.cloudflare.com/*' => Http::response(['iceServers' => [
        ['urls' => ['stun:stun.cloudflare.com:3478', 'stun:stun.cloudflare.com:53']],
        ['urls' => ['turn:turn.cloudflare.com:3478?transport=udp', 'turn:turn.cloudflare.com:53?transport=udp', 'turns:turn.cloudflare.com:443?transport=tcp'], 'username' => 'u', 'credential' => 'c'],
    ]])]);
}

test('TURN is off until the Cloudflare key is configured', function () {
    $room = turnRoom();
    expect($room['turnAvailable'])->toBeFalse();

    requestTurn($room)->assertStatus(503)->assertJson(['reason' => 'turn_disabled']);
});

test('a room gets short-lived TURN credentials without the browser-blocked port 53', function () {
    enableTurn();
    $room = turnRoom();
    expect($room['turnAvailable'])->toBeTrue()->and($room['maxRelayBytes'])->toBe(2 * 1024 ** 3);

    $servers = requestTurn($room)->assertOk()->json('iceServers');

    expect($servers)->toBe([
        ['urls' => ['stun:stun.cloudflare.com:3478', 'stun:stun.l.google.com:19302']],
        ['urls' => ['stun:stun.cloudflare.com:3478']],
        ['urls' => ['turn:turn.cloudflare.com:3478?transport=udp', 'turns:turn.cloudflare.com:443?transport=tcp'], 'username' => 'u', 'credential' => 'c'],
    ]);

    Http::assertSent(fn (ClientRequest $request) => $request->url() === 'https://rtc.live.cloudflare.com/v1/turn/keys/key-123/credentials/generate-ice-servers'
        && $request->hasHeader('Authorization', 'Bearer secret-token')
        && $request['ttl'] === 3600);
});

test('the secret token never reaches the browser', function () {
    enableTurn();
    $room = turnRoom();

    expect(json_encode($room))->not->toContain('secret-token')
        ->and(requestTurn($room)->getContent())->not->toContain('secret-token');
});

test('each IP gets a limited number of relayed sessions per day', function () {
    enableTurn();

    requestTurn(turnRoom())->assertOk();
    requestTurn(turnRoom())->assertOk();
    requestTurn(turnRoom())->assertStatus(429)->assertJson(['reason' => 'turn_daily_limit']);

    $this->travel(25)->hours();
    requestTurn(turnRoom())->assertOk();
});

test('relayed sessions stop for everyone once the monthly budget is used', function () {
    enableTurn();
    config(['services.transfer.turn_daily_per_ip' => 100]);

    foreach (range(1, 3) as $ignored) {
        requestTurn(turnRoom())->assertOk();
    }
    requestTurn(turnRoom())->assertStatus(429)->assertJson(['reason' => 'turn_monthly_limit']);

    $this->travel(1)->months();
    requestTurn(turnRoom())->assertOk();
});

test('a Cloudflare outage gives a clear message and does not use up a session', function () {
    config([
        'services.transfer.turn_key_id' => 'key-123',
        'services.transfer.turn_api_token' => 'secret-token',
        'services.transfer.turn_daily_per_ip' => 2,
    ]);
    Http::fake(['rtc.live.cloudflare.com/*' => Http::sequence()
        ->push('oops', 500)
        ->push('oops', 500)
        ->push(['iceServers' => [['urls' => ['turn:turn.cloudflare.com:3478'], 'username' => 'u', 'credential' => 'c']]])
        ->push(['iceServers' => [['urls' => ['turn:turn.cloudflare.com:3478'], 'username' => 'u', 'credential' => 'c']]])]);

    requestTurn(turnRoom())->assertStatus(503)->assertJson(['reason' => 'turn_unavailable']);
    requestTurn(turnRoom())->assertStatus(503);

    // Hai lần lỗi không bị tính: vẫn còn đủ 2 lượt trong ngày.
    requestTurn(turnRoom())->assertOk();
    requestTurn(turnRoom())->assertOk();
});

test('only devices in the room can ask for TURN', function () {
    enableTurn();
    $room = turnRoom();

    $this->postJson(route('transfer.rooms.turn', $room['code']), [], ['X-Room-Token' => 'nope'])->assertForbidden();
    Http::assertNothingSent();
});
