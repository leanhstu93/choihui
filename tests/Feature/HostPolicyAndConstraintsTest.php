<?php

declare(strict_types=1);

use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Models\HostEntry;
use App\Models\HostSlot;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Kỳ do một tay của dây chủ hụi hốt.
 */
function roundWonByHostSlot(HostSlot $hostSlot, int $roundNo = 1): HuiRound
{
    return HuiRound::factory()->create([
        'group_id' => $hostSlot->group_id,
        'user_id' => $hostSlot->user_id,
        'round_no' => $roundNo,
        'bid_amount' => 100_000,
        'winner_type' => WinnerType::NguoiKhac,
        'winner_host_slot_id' => $hostSlot->id,
    ]);
}

test('owners may act on host rows and other users may not', function (Closure $makeModel, array $abilities) {
    $owner = User::factory()->host()->create();
    $model = $makeModel($owner);
    $otherUser = User::factory()->host()->create();

    foreach ($abilities as $ability) {
        expect(Gate::forUser($owner)->allows($ability, $model))->toBeTrue("owner should be allowed to {$ability}")
            ->and(Gate::forUser($otherUser)->allows($ability, $model))->toBeFalse("other user should not be allowed to {$ability}");
    }
})->with([
    'member' => [fn (User $owner) => Member::factory()->for($owner)->create(), ['view', 'update', 'delete']],
    'host slot' => [fn (User $owner) => HostSlot::factory()->for(HuiGroup::factory()->for($owner), 'group')->create(), ['view', 'update']],
    'host entry' => [fn (User $owner) => HostEntry::factory()->for(HostSlot::factory()->for(HuiGroup::factory()->for($owner), 'group'))->create(), ['view', 'update']],
]);

test('a member who has held a slot cannot be deleted, even after the group ends', function () {
    $this->actingAs($owner = User::factory()->host()->create());
    $hostSlot = HostSlot::factory()->for(HuiGroup::factory()->for($owner), 'group')->create();

    $response = Gate::forUser($owner)->inspect('delete', $hostSlot->member);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe('Hụi viên đã giữ tay trong dây nên không thể xóa.');

    $hostSlot->group->forceFill(['status' => HuiGroupStatus::KetThuc])->save();

    expect(Gate::forUser($owner)->allows('delete', $hostSlot->member))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('delete', Member::factory()->for($owner)->create()))->toBeTrue();
});

test('a slot that has already won cannot change holder', function () {
    $owner = User::factory()->host()->create();
    $hostSlot = HostSlot::factory()->for(HuiGroup::factory()->for($owner), 'group')->create();
    roundWonByHostSlot($hostSlot);

    $response = Gate::forUser($owner)->inspect('update', $hostSlot);

    expect($response->denied())->toBeTrue()
        ->and($response->message())->toBe('Tay này đã hốt nên không thể đổi người giữ.');
});

test('slot numbers are unique within a group', function () {
    $hostSlot = HostSlot::factory()->create(['slot_no' => 1]);

    HostSlot::factory()->create(['group_id' => $hostSlot->group_id, 'slot_no' => 1]);
})->throws(QueryException::class);

test('a host slot can win at most one round', function () {
    $hostSlot = HostSlot::factory()->create();
    roundWonByHostSlot($hostSlot, 1);

    roundWonByHostSlot($hostSlot, 2);
})->throws(QueryException::class);

test('a host slot has at most one entry per round', function () {
    $entry = HostEntry::factory()->create();

    HostEntry::factory()->create(['round_id' => $entry->round_id, 'host_slot_id' => $entry->host_slot_id]);
})->throws(QueryException::class);

test('a member holding a slot cannot be deleted directly', function () {
    $hostSlot = HostSlot::factory()->create();

    DB::table('members')->where('id', $hostSlot->member_id)->delete();
})->throws(QueryException::class);

test('host check constraints reject invalid values', function (Closure $write) {
    $write();
})->with([
    'unknown role' => [fn () => DB::table('users')->where('id', User::factory()->create()->id)->update(['role' => 'admin'])],
    'slot number zero' => [fn () => HostSlot::factory()->create(['slot_no' => 0])],
    'host winner marked as mine' => [function () {
        $hostSlot = HostSlot::factory()->create();
        DB::table('hui_rounds')->where('id', HuiRound::factory()->create(['group_id' => $hostSlot->group_id, 'user_id' => $hostSlot->user_id])->id)
            ->update(['winner_host_slot_id' => $hostSlot->id, 'winner_type' => WinnerType::ChuaCo->value]);
    }],
])->throws(QueryException::class);
