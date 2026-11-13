<?php

declare(strict_types=1);

use App\Domain\RoomCode;
use App\Models\TransferRoom;
use App\Models\TransferSignal;
use Illuminate\Console\Scheduling\Schedule;

/*
 * Mai mối cho gửi file trực tiếp: phòng 2 thiết bị, mã hết hạn, tin nhắn bắt tay không lưu lại.
 */

function createRoom(): array
{
    return test()->postJson(route('transfer.rooms.store'))->assertCreated()->json();
}

function joinRoom(string $code): array
{
    return test()->postJson(route('transfer.rooms.join', $code))->assertOk()->json();
}

test('a sender creates a room with a short readable code and a join link', function () {
    $room = createRoom();

    expect($room['code'])->toHaveLength(6)
        ->and(RoomCode::normalize($room['code']))->toBe($room['code'])
        ->and($room['role'])->toBe('sender')
        ->and($room['token'])->toHaveLength(48)
        ->and($room['peerJoined'])->toBeFalse()
        ->and($room['joinUrl'])->toBe(route('transfer.show', ['ma' => $room['code']]))
        ->and($room['qrSvg'])->toStartWith('<svg')
        ->and($room['iceServers'][0]['urls'])->toContain('stun:stun.cloudflare.com:3478');

    // Chỉ lưu bản băm của token.
    expect(TransferRoom::query()->sole()->sender_token_hash)->toBe(hash('sha256', $room['token']));
});

test('room codes avoid look-alike characters and accept sloppy typing', function () {
    foreach (range(1, 200) as $ignored) {
        expect(RoomCode::generate())->toMatch('/^[ABCDEFGHJKMNPQRSTUVWXYZ2-9]{6}$/');
    }

    expect(RoomCode::normalize(' ab3-k9z '))->toBe('AB3K9Z')
        ->and(RoomCode::normalize('AB0K9Z'))->toBeNull()
        ->and(RoomCode::normalize('ABCDE'))->toBeNull();
});

test('only one other device can join a room', function () {
    $room = createRoom();

    $receiver = joinRoom(strtolower($room['code']));
    expect($receiver['role'])->toBe('receiver')->and($receiver['peerJoined'])->toBeTrue();

    $this->postJson(route('transfer.rooms.join', $room['code']))->assertStatus(409)->assertJson(['reason' => 'full']);
});

test('signals reach only the other device and are deleted once acknowledged', function () {
    $room = createRoom();
    $receiver = joinRoom($room['code']);
    $asSender = ['X-Room-Token' => $room['token']];
    $asReceiver = ['X-Room-Token' => $receiver['token']];

    $this->postJson(route('transfer.rooms.signal', $room['code']), ['payload' => ['type' => 'offer', 'sdp' => 'v=0']], $asSender)->assertCreated();
    $this->postJson(route('transfer.rooms.signal', $room['code']), ['payload' => ['type' => 'candidate', 'candidate' => 'x']], $asSender)->assertCreated();

    // Máy gửi không nhận lại tin của chính mình.
    $this->getJson(route('transfer.rooms.poll', $room['code']), $asSender)->assertOk()->assertJsonCount(0, 'signals');

    $signals = $this->getJson(route('transfer.rooms.poll', $room['code']), $asReceiver)->assertOk()->assertJsonCount(2, 'signals')->json('signals');
    expect($signals[0]['payload'])->toBe(['type' => 'offer', 'sdp' => 'v=0']);

    $this->getJson(route('transfer.rooms.poll', [$room['code'], 'after' => $signals[1]['id']]), $asReceiver)->assertOk()->assertJsonCount(0, 'signals');
    expect(TransferSignal::query()->count())->toBe(0);
});

test('signals are relayed byte for byte, including trailing line breaks of the SDP', function () {
    $room = createRoom();
    $receiver = joinRoom($room['code']);
    $sdp = "v=0\r\na=max-message-size:262144\r\n";

    $this->postJson(route('transfer.rooms.signal', $room['code']), ['payload' => ['type' => 'description', 'description' => ['type' => 'offer', 'sdp' => $sdp]]], ['X-Room-Token' => $room['token']])->assertCreated();

    expect($this->getJson(route('transfer.rooms.poll', $room['code']), ['X-Room-Token' => $receiver['token']])->json('signals.0.payload.description.sdp'))->toBe($sdp);
});

test('a wrong token cannot read or write the room', function () {
    $room = createRoom();

    $this->getJson(route('transfer.rooms.poll', $room['code']), ['X-Room-Token' => 'nope'])->assertForbidden()->assertJson(['reason' => 'forbidden']);
    $this->postJson(route('transfer.rooms.signal', $room['code']), ['payload' => ['a' => 1]])->assertForbidden();
});

test('signals must be small objects and pending ones are capped', function () {
    $room = createRoom();
    $headers = ['X-Room-Token' => $room['token']];

    $this->postJson(route('transfer.rooms.signal', $room['code']), ['payload' => 'text'], $headers)->assertStatus(422);
    $this->postJson(route('transfer.rooms.signal', $room['code']), ['payload' => ['sdp' => str_repeat('a', 70000)]], $headers)->assertStatus(422);

    $roomModel = TransferRoom::query()->sole();
    foreach (range(1, 200) as $index) {
        $roomModel->signals()->create(['recipient' => 'receiver', 'payload' => ['n' => $index]]);
    }
    $this->postJson(route('transfer.rooms.signal', $room['code']), ['payload' => ['n' => 201]], $headers)->assertStatus(429)->assertJson(['reason' => 'too_many_signals']);
});

test('a room expires after ten idle minutes and can no longer be joined or used', function () {
    $room = createRoom();

    $this->travel(9)->minutes();
    $this->getJson(route('transfer.rooms.poll', $room['code']), ['X-Room-Token' => $room['token']])->assertOk();

    // Mỗi lần hỏi giữ phòng thêm 10 phút.
    $this->travel(9)->minutes();
    $this->getJson(route('transfer.rooms.poll', $room['code']), ['X-Room-Token' => $room['token']])->assertOk();

    $this->travel(11)->minutes();
    $this->postJson(route('transfer.rooms.join', $room['code']))->assertStatus(410)->assertJson(['reason' => 'expired']);
    $this->getJson(route('transfer.rooms.poll', $room['code']), ['X-Room-Token' => $room['token']])->assertStatus(410);

    $this->artisan('transfer:prune')->assertSuccessful();
    expect(TransferRoom::query()->count())->toBe(0);
    $this->postJson(route('transfer.rooms.join', $room['code']))->assertNotFound();
});

test('guessing codes is rate limited per IP', function () {
    foreach (range(1, 10) as $ignored) {
        $this->postJson(route('transfer.rooms.join', 'AAAAAA'))->assertNotFound();
    }

    $room = createRoom();
    $this->postJson(route('transfer.rooms.join', $room['code']))->assertStatus(429)->assertJson(['reason' => 'too_many_attempts']);
});

test('either device can close the room, which removes everything', function () {
    $room = createRoom();
    $receiver = joinRoom($room['code']);
    $this->postJson(route('transfer.rooms.signal', $room['code']), ['payload' => ['a' => 1]], ['X-Room-Token' => $room['token']]);

    $this->deleteJson(route('transfer.rooms.destroy', $room['code']), [], ['X-Room-Token' => $receiver['token']])->assertNoContent();

    expect(TransferRoom::query()->count())->toBe(0)->and(TransferSignal::query()->count())->toBe(0);
});

test('the transfer api keeps no session and expired rooms are pruned every five minutes', function () {
    $this->postJson(route('transfer.rooms.store'))->assertCreated()->assertCookieMissing(config('session.cookie'));

    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command, 'transfer:prune'));
    expect($event->expression)->toBe('*/5 * * * *');
});

test('the transfer page is public and reassures that files are not stored', function () {
    $this->get(route('transfer.show'))->assertOk()
        ->assertSee('Gửi file giữa hai thiết bị')
        ->assertSee('File đi thẳng giữa hai thiết bị, được mã hóa, không lưu trên server.')
        ->assertSee('<meta name="robots" content="index">', false);

    // Link từ mã QR: không cho Google lập chỉ mục.
    $this->get(route('transfer.show', ['ma' => 'ABCDEF']))->assertOk()->assertSee('<meta name="robots" content="noindex">', false);
});
