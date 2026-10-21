<?php

declare(strict_types=1);

use App\Http\Requests\AssignHostSlotRequest;
use App\Http\Requests\UpdateHostEntryRequest;
use App\Models\HostEntry;
use App\Models\HostSlot;
use App\Models\HuiGroup;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * Phân quyền 3 lớp cho dữ liệu chủ hụi (document/04-chu-hui.md mục 6, ĐT mục 8)
 * và tách vai trò tài khoản (C9). Route dưới đây chỉ dùng trong test, chạy qua
 * middleware, route model binding và Form Request như route thật.
 */
beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:chu_hui'])->prefix('_test/host')->group(function () {
        Route::put('slots/{hostSlot}', fn (AssignHostSlotRequest $request, HostSlot $hostSlot) => response()->noContent());
        Route::put('entries/{hostEntry}', fn (UpdateHostEntryRequest $request, HostEntry $hostEntry) => response()->noContent());
    });
});

/**
 * Tạo một dây chủ hụi đủ ba bảng mới cho một tài khoản chủ hụi.
 *
 * @return array{group: HuiGroup, member: Member, hostSlot: HostSlot, hostEntry: HostEntry}
 */
function hostDataOwnedBy(User $user): array
{
    $group = HuiGroup::factory()->for($user)->create(['total_slots' => 10]);
    $member = Member::factory()->for($user)->create();
    $hostSlot = HostSlot::factory()->for($group, 'group')->for($member)->create(['slot_no' => 1]);
    $hostEntry = HostEntry::factory()->for($hostSlot)->create();

    return compact('group', 'member', 'hostSlot', 'hostEntry');
}

test('host model queries only return rows owned by the signed-in user', function () {
    $alice = User::factory()->host()->create();
    $aliceData = hostDataOwnedBy($alice);
    hostDataOwnedBy(User::factory()->host()->create());

    $this->actingAs($alice);

    expect(Member::pluck('id')->all())->toBe([$aliceData['member']->id])
        ->and(HostSlot::pluck('id')->all())->toBe([$aliceData['hostSlot']->id])
        ->and(HostEntry::pluck('id')->all())->toBe([$aliceData['hostEntry']->id]);
});

test('host model queries return nothing when no one is signed in', function () {
    hostDataOwnedBy(User::factory()->host()->create());

    expect(Member::count())->toBe(0)
        ->and(HostSlot::count())->toBe(0)
        ->and(HostEntry::count())->toBe(0);
});

test('another host\'s rows return 404 on update', function (string $key, string $uri, array $payload) {
    $bobData = hostDataOwnedBy(User::factory()->host()->create());
    $alice = User::factory()->host()->create();
    $payload = $key === 'hostSlot' ? ['member_id' => Member::factory()->for($alice)->create()->id] : $payload;

    $this->actingAs($alice)
        ->put(str_replace('{id}', $bobData[$key]->id, $uri), $payload)
        ->assertNotFound();
})->with([
    'host slot' => ['hostSlot', '/_test/host/slots/{id}', []],
    'host entry' => ['hostEntry', '/_test/host/entries/{id}', ['amount_actual' => 0]],
]);

test('a slot cannot be assigned to another host\'s member', function () {
    $alice = User::factory()->host()->create();
    $aliceData = hostDataOwnedBy($alice);
    $bobMember = Member::factory()->for(User::factory()->host())->create();

    $this->actingAs($alice)
        ->put("/_test/host/slots/{$aliceData['hostSlot']->id}", ['member_id' => $bobMember->id])
        ->assertSessionHasErrors(['member_id' => 'Hụi viên không tồn tại.']);
});

test('a host can assign their own member to their own slot', function () {
    $alice = User::factory()->host()->create();
    $aliceData = hostDataOwnedBy($alice);

    $this->actingAs($alice)
        ->put("/_test/host/slots/{$aliceData['hostSlot']->id}", ['member_id' => Member::factory()->for($alice)->create()->id])
        ->assertNoContent();
});

test('each role gets 404 on the other role\'s pages', function () {
    $player = User::factory()->create();
    $host = User::factory()->host()->create();
    $hostData = hostDataOwnedBy($host);

    $this->actingAs($host)->get(route('groups.index'))->assertNotFound();
    $this->actingAs($host)->get(route('reports'))->assertNotFound();
    $this->actingAs($player)->put("/_test/host/entries/{$hostData['hostEntry']->id}", ['amount_actual' => 0])->assertNotFound();
    $this->actingAs($player)->get(route('groups.index'))->assertOk();
});

test('new accounts are players by default', function () {
    expect(User::factory()->create()->refresh()->isHost())->toBeFalse()
        ->and(User::factory()->host()->create()->refresh()->isHost())->toBeTrue();
});
