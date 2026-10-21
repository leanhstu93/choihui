<?php

declare(strict_types=1);

use App\Enums\WinnerType;
use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Ràng buộc ở đặc tả mục 14: database tự từ chối dữ liệu sai kể cả khi code có lỗi.
 */

test('a slot can win at most one round', function () {
    $slot = MySlot::factory()->create();
    HuiRound::factory()->wonBy($slot)->create(['round_no' => 1]);

    HuiRound::factory()->wonBy($slot)->create(['round_no' => 2]);
})->throws(QueryException::class);

test('round numbers are unique within a group', function () {
    $group = HuiGroup::factory()->create();
    HuiRound::factory()->for($group, 'group')->create(['round_no' => 1]);

    HuiRound::factory()->for($group, 'group')->create(['round_no' => 1]);
})->throws(QueryException::class);

test('slot labels are unique within a group', function () {
    $group = HuiGroup::factory()->create();
    MySlot::factory()->for($group, 'group')->create(['label' => 'Tay 1']);

    MySlot::factory()->for($group, 'group')->create(['label' => 'Tay 1']);
})->throws(QueryException::class);

test('a slot has at most one cash entry per round', function () {
    $entry = CashEntry::factory()->create();

    CashEntry::factory()->create(['round_id' => $entry->round_id, 'slot_id' => $entry->slot_id]);
})->throws(QueryException::class);

test('a slot that has won cannot be deleted directly', function () {
    $slot = MySlot::factory()->create();
    HuiRound::factory()->wonBy($slot)->create();

    $slot->delete();
})->throws(QueryException::class);

test('check constraints reject invalid values', function (string $table, Closure $makeRow) {
    DB::table($table)->insert($makeRow());
})->with([
    'face value of zero' => ['hui_groups', fn () => array_merge(
        HuiGroup::factory()->raw(['face_value' => 0]),
        ['id' => (string) Str::ulid(), 'user_id' => User::factory()->create()->id],
    )],
    'only one slot in group' => ['hui_groups', fn () => array_merge(
        HuiGroup::factory()->raw(['total_slots' => 1]),
        ['id' => (string) Str::ulid(), 'user_id' => User::factory()->create()->id],
    )],
    'my winner without slot' => ['hui_rounds', fn () => array_merge(
        HuiRound::factory()->raw(['winner_type' => WinnerType::Minh->value]),
        ['id' => (string) Str::ulid()],
    )],
])->throws(QueryException::class);
