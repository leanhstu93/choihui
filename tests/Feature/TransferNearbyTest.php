<?php

declare(strict_types=1);

use App\Domain\NetworkKey;
use App\Models\TransferRoom;

/*
 * Tự thấy máy cùng mạng (cùng IP công cộng): chỉ hiện tên ngẫu nhiên, mời thì máy kia phải đồng ý.
 */

function roomFrom(string $ip): array
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])->postJson(route('transfer.rooms.store'))->assertCreated()->json();
}

function pollNearby(array $room, string $ip, string $name = 'Rùa Lam', string $device = 'iPhone', bool $nearby = true)
{
    return test()->withServerVariables(['REMOTE_ADDR' => $ip])
        ->getJson(route('transfer.rooms.poll', ['code' => $room['code'], 'after' => 0, 'nearby' => $nearby ? 1 : 0, 'name' => $name, 'device' => $device]), ['X-Room-Token' => $room['token']])
        ->assertOk();
}

test('devices on the same network see each other by random name only', function () {
    $home = '203.0.113.7';
    $laptop = roomFrom($home);
    $phone = roomFrom($home);
    $stranger = roomFrom('198.51.100.9');

    pollNearby($laptop, $home, 'Mèo Xanh', 'Máy Windows');
    pollNearby($stranger, '198.51.100.9', 'Cáo Đỏ', 'Máy Mac');
    $seen = pollNearby($phone, $home)->json('nearby.devices');

    expect($seen)->toHaveCount(1)
        ->and($seen[0])->toHaveKeys(['id', 'name', 'device'])
        ->and($seen[0]['name'])->toBe('Mèo Xanh')
        ->and($seen[0]['device'])->toBe('Máy Windows')
        ->and(json_encode($seen))->not->toContain($laptop['code']);

    // Không lưu IP gốc.
    expect(TransferRoom::query()->where('code', $laptop['code'])->value('network_hash'))
        ->toBe(NetworkKey::of($home, (string) config('app.key')))
        ->not->toContain('203.0.113');
});

test('a device that stopped polling, opted out or got connected disappears from the list', function () {
    $home = '203.0.113.7';
    $a = roomFrom($home);
    $b = roomFrom($home);
    $c = roomFrom($home);

    pollNearby($a, $home, 'A');
    pollNearby($b, $home, 'B');
    pollNearby($c, $home, 'C', nearby: false);
    expect(collect(pollNearby($a, $home, 'A')->json('nearby.devices'))->pluck('name')->all())->toBe(['B']);

    // B đã kết nối với máy khác.
    $this->postJson(route('transfer.rooms.join', $b['code']))->assertOk();
    expect(pollNearby($a, $home, 'A')->json('nearby.devices'))->toBe([]);

    $d = roomFrom($home);
    pollNearby($d, $home, 'D');
    $this->travel(TransferRoom::NEARBY_FRESH_SECONDS + 1)->seconds();
    expect(pollNearby($a, $home, 'A')->json('nearby.devices'))->toBe([]);
});

test('an invited device sees who invites it and joins only when it accepts', function () {
    $home = '203.0.113.7';
    $laptop = roomFrom($home);
    $phone = roomFrom($home);
    pollNearby($phone, $home, 'Rùa Lam', 'iPhone');
    $phoneId = collect(pollNearby($laptop, $home, 'Mèo Xanh', 'Máy Windows')->json('nearby.devices'))->firstWhere('name', 'Rùa Lam')['id'];

    $this->withServerVariables(['REMOTE_ADDR' => $home])
        ->postJson(route('transfer.rooms.invite', $laptop['code']), ['to' => $phoneId], ['X-Room-Token' => $laptop['token']])
        ->assertNoContent();

    $invite = pollNearby($phone, $home, 'Rùa Lam', 'iPhone')->json('nearby.invite');
    expect($invite)->toMatchArray(['name' => 'Mèo Xanh', 'device' => 'Máy Windows', 'code' => $laptop['code']]);

    // Chưa đồng ý thì chưa ai vào phòng.
    expect(TransferRoom::query()->where('code', $laptop['code'])->value('receiver_token_hash'))->toBeNull();

    $this->postJson(route('transfer.rooms.join', $invite['code']))->assertOk()->assertJson(['role' => 'receiver']);
});

test('declining tells the inviter once', function () {
    $home = '203.0.113.7';
    $laptop = roomFrom($home);
    $phone = roomFrom($home);
    pollNearby($phone, $home, 'Rùa Lam');
    $phoneId = collect(pollNearby($laptop, $home, 'Mèo Xanh')->json('nearby.devices'))->firstWhere('name', 'Rùa Lam')['id'];

    $this->withServerVariables(['REMOTE_ADDR' => $home])
        ->postJson(route('transfer.rooms.invite', $laptop['code']), ['to' => $phoneId], ['X-Room-Token' => $laptop['token']])->assertNoContent();
    $this->postJson(route('transfer.rooms.decline', $phone['code']), [], ['X-Room-Token' => $phone['token']])->assertNoContent();

    expect(pollNearby($phone, $home, 'Rùa Lam')->json('nearby.invite'))->toBeNull()
        ->and(pollNearby($laptop, $home, 'Mèo Xanh')->json('nearby.declinedBy'))->toBe('Rùa Lam')
        ->and(pollNearby($laptop, $home, 'Mèo Xanh')->json('nearby.declinedBy'))->toBeNull();
});

test('invites only reach fresh devices on the same network, and only from a waiting sender', function () {
    $laptop = roomFrom('203.0.113.7');
    $far = roomFrom('198.51.100.9');
    pollNearby($laptop, '203.0.113.7', 'Mèo Xanh');
    pollNearby($far, '198.51.100.9', 'Cáo Đỏ');
    $farId = TransferRoom::query()->where('code', $far['code'])->value('nearby_id');

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->postJson(route('transfer.rooms.invite', $laptop['code']), ['to' => $farId], ['X-Room-Token' => $laptop['token']])
        ->assertNotFound()->assertJson(['reason' => 'nearby_gone']);

    $receiver = $this->postJson(route('transfer.rooms.join', $laptop['code']))->json();
    $this->postJson(route('transfer.rooms.invite', $laptop['code']), ['to' => $farId], ['X-Room-Token' => $receiver['token']])
        ->assertForbidden();
});

test('network keys group IPv6 by /64 and treat mapped IPv4 as IPv4', function () {
    expect(NetworkKey::of('2001:db8:1:2::10', 's'))->toBe(NetworkKey::of('2001:db8:1:2:aaaa::99', 's'))
        ->and(NetworkKey::of('2001:db8:1:2::10', 's'))->not->toBe(NetworkKey::of('2001:db8:1:3::10', 's'))
        ->and(NetworkKey::of('::ffff:203.0.113.7', 's'))->toBe(NetworkKey::of('203.0.113.7', 's'))
        ->and(NetworkKey::of('not an ip', 's'))->toBeNull();
});
