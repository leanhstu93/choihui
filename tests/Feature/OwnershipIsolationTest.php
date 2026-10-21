<?php

declare(strict_types=1);

use App\Enums\CashDirection;
use App\Enums\WinnerType;
use App\Http\Requests\StoreCashEntryRequest;
use App\Http\Requests\StoreMySlotRequest;
use App\Http\Requests\UpdateCashEntryRequest;
use App\Http\Requests\UpdateHuiGroupRequest;
use App\Http\Requests\UpdateHuiRoundRequest;
use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * Các ca kiểm thử bắt buộc ở đặc tả mục 14. Màn hình thật làm ở giai đoạn 4,
 * nên các route dưới đây chỉ dùng trong test: chúng chạy qua middleware auth,
 * route model binding (global scope) và Form Request giống route thật.
 */
beforeEach(function () {
    Route::middleware(['web', 'auth'])->prefix('_test')->group(function () {
        Route::put('groups/{group}', fn (UpdateHuiGroupRequest $request, HuiGroup $group) => response()->noContent());
        Route::post('slots', fn (StoreMySlotRequest $request) => response()->noContent());
        Route::put('rounds/{round}', fn (UpdateHuiRoundRequest $request, HuiRound $round) => response()->noContent());
        Route::post('cash-entries', fn (StoreCashEntryRequest $request) => response()->noContent());
        Route::put('cash-entries/{cashEntry}', fn (UpdateCashEntryRequest $request, CashEntry $cashEntry) => response()->noContent());
    });
});

/**
 * Tạo một dây đủ bốn bảng cho một người dùng.
 *
 * @return array{group: HuiGroup, slot: MySlot, round: HuiRound, cashEntry: CashEntry}
 */
function huiDataOwnedBy(User $user): array
{
    $group = HuiGroup::factory()->for($user)->create(['total_slots' => 10, 'face_value' => 1_000_000]);
    $slot = MySlot::factory()->for($group, 'group')->create();
    $round = HuiRound::factory()->for($group, 'group')->create();
    $cashEntry = CashEntry::factory()->for($round, 'round')->for($slot, 'slot')->create();

    return compact('group', 'slot', 'round', 'cashEntry');
}

test('model queries only return rows owned by the signed-in user', function () {
    $alice = User::factory()->create();
    $aliceData = huiDataOwnedBy($alice);
    huiDataOwnedBy(User::factory()->create());

    $this->actingAs($alice);

    expect(HuiGroup::pluck('id')->all())->toBe([$aliceData['group']->id])
        ->and(MySlot::pluck('id')->all())->toBe([$aliceData['slot']->id])
        ->and(HuiRound::pluck('id')->all())->toBe([$aliceData['round']->id])
        ->and(CashEntry::pluck('id')->all())->toBe([$aliceData['cashEntry']->id]);
});

test('model queries return nothing when no one is signed in', function () {
    huiDataOwnedBy(User::factory()->create());

    expect(HuiGroup::count())->toBe(0)
        ->and(MySlot::count())->toBe(0)
        ->and(HuiRound::count())->toBe(0)
        ->and(CashEntry::count())->toBe(0);
});

test('new rows are always assigned to the signed-in user', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $this->actingAs($alice);

    $group = (new HuiGroup)->forceFill([
        'user_id' => $bob->id,
        'name' => 'Hụi 1tr cô Hoa',
        'face_value' => 1_000_000,
        'total_slots' => 10,
        'start_date' => '2026-11-05',
        'host_name' => 'Cô Hoa',
    ]);
    $group->save();

    expect($group->user_id)->toBe($alice->id);
});

test('guests are redirected to the login page', function () {
    $round = huiDataOwnedBy(User::factory()->create())['round'];

    $this->put("/_test/rounds/{$round->id}", ['winner_type' => 'chua_co'])
        ->assertRedirect(route('login'));
});

test('another user\'s rows return 404 on update', function (string $uri, array $payload) {
    $bobData = huiDataOwnedBy(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->put(str_replace(['{group}', '{round}', '{cashEntry}'], [$bobData['group']->id, $bobData['round']->id, $bobData['cashEntry']->id], $uri), $payload)
        ->assertNotFound();
})->with([
    'group' => ['/_test/groups/{group}', ['name' => 'X', 'host_name' => 'Y', 'status' => 'dang_chay']],
    'round' => ['/_test/rounds/{round}', ['winner_type' => 'chua_co']],
    'cash entry' => ['/_test/cash-entries/{cashEntry}', ['amount_actual' => 0, 'status' => 'chua']],
]);

test('owners can update their own rows', function () {
    $alice = User::factory()->create();
    $aliceData = huiDataOwnedBy($alice);

    $this->actingAs($alice)
        ->put("/_test/rounds/{$aliceData['round']->id}", [
            'bid_amount' => 100_000,
            'winner_type' => WinnerType::Minh->value,
            'winner_slot_id' => $aliceData['slot']->id,
        ])
        ->assertNoContent();
});

test('a slot cannot be added to another user\'s group', function () {
    $bobGroup = huiDataOwnedBy(User::factory()->create())['group'];

    $this->actingAs(User::factory()->create())
        ->post('/_test/slots', ['group_id' => $bobGroup->id, 'label' => 'Tay 9'])
        ->assertInvalid(['group_id' => 'Dây hụi không tồn tại.']);
});

test('a slot can be added to the user\'s own group', function () {
    $alice = User::factory()->create();
    $aliceGroup = huiDataOwnedBy($alice)['group'];

    $this->actingAs($alice)
        ->post('/_test/slots', ['group_id' => $aliceGroup->id, 'label' => 'Tay mới'])
        ->assertNoContent();
});

test('slots cannot exceed the number of slots in the group', function () {
    $alice = User::factory()->create();
    $group = HuiGroup::factory()->for($alice)->create(['total_slots' => 2]);
    MySlot::factory()->count(2)->for($group, 'group')->create();

    $this->actingAs($alice)
        ->post('/_test/slots', ['group_id' => $group->id, 'label' => 'Tay 3'])
        ->assertInvalid(['label' => 'Số tay của mình không được vượt quá tổng số tay của dây.']);
});

test('the round winner must be the user\'s own slot in the same group', function (Closure $makeSlot) {
    $alice = User::factory()->create();
    $round = huiDataOwnedBy($alice)['round'];
    $slot = $makeSlot($alice);

    $this->actingAs($alice)
        ->put("/_test/rounds/{$round->id}", [
            'bid_amount' => 100_000,
            'winner_type' => WinnerType::Minh->value,
            'winner_slot_id' => $slot->id,
        ])
        ->assertInvalid(['winner_slot_id' => 'Tay hốt không thuộc dây này.']);
})->with([
    'slot of another user' => [fn (User $alice) => huiDataOwnedBy(User::factory()->create())['slot']],
    'own slot in another group' => [fn (User $alice) => MySlot::factory()->for(HuiGroup::factory()->for($alice), 'group')->create()],
]);

test('a slot that already won cannot win another round', function () {
    $alice = User::factory()->create();
    $aliceData = huiDataOwnedBy($alice);
    HuiRound::factory()->wonBy($aliceData['slot'])->create();

    $this->actingAs($alice)
        ->put("/_test/rounds/{$aliceData['round']->id}", [
            'bid_amount' => 100_000,
            'winner_type' => WinnerType::Minh->value,
            'winner_slot_id' => $aliceData['slot']->id,
        ])
        ->assertInvalid(['winner_slot_id' => 'Tay này đã hốt ở một kỳ khác.']);
});

test('the bid amount cannot exceed the face value', function () {
    $alice = User::factory()->create();
    $round = huiDataOwnedBy($alice)['round'];

    $this->actingAs($alice)
        ->put("/_test/rounds/{$round->id}", ['bid_amount' => 1_000_001, 'winner_type' => WinnerType::ChuaCo->value])
        ->assertInvalid(['bid_amount' => 'Lãi thăm không được vượt quá mệnh giá.']);
});

test('a cash entry cannot reference another user\'s round or slot', function (string $field, string $message) {
    $alice = User::factory()->create();
    $aliceData = huiDataOwnedBy($alice);
    $bobData = huiDataOwnedBy(User::factory()->create());

    $payload = [
        'round_id' => $aliceData['round']->id,
        'slot_id' => MySlot::factory()->for($aliceData['group'], 'group')->create()->id,
        'direction' => CashDirection::Dong->value,
        'amount_expected' => 900_000,
    ];
    $payload[$field] = $field === 'round_id' ? $bobData['round']->id : $bobData['slot']->id;

    $this->actingAs($alice)
        ->post('/_test/cash-entries', $payload)
        ->assertInvalid([$field => $message]);
})->with([
    'round of another user' => ['round_id', 'Kỳ hụi không tồn tại.'],
    'slot of another user' => ['slot_id', 'Tay hụi không tồn tại.'],
]);

test('a cash entry\'s round and slot must belong to the same group', function () {
    $alice = User::factory()->create();
    $aliceData = huiDataOwnedBy($alice);
    $otherSlot = MySlot::factory()->for(HuiGroup::factory()->for($alice), 'group')->create();

    $this->actingAs($alice)
        ->post('/_test/cash-entries', [
            'round_id' => $aliceData['round']->id,
            'slot_id' => $otherSlot->id,
            'direction' => CashDirection::Dong->value,
            'amount_expected' => 900_000,
        ])
        ->assertInvalid(['slot_id' => 'Tay và kỳ phải thuộc cùng một dây hụi.']);
});

test('a cash entry can be recorded for the user\'s own round and slot', function () {
    $alice = User::factory()->create();
    $aliceData = huiDataOwnedBy($alice);
    $newSlot = MySlot::factory()->for($aliceData['group'], 'group')->create();

    $this->actingAs($alice)
        ->post('/_test/cash-entries', [
            'round_id' => $aliceData['round']->id,
            'slot_id' => $newSlot->id,
            'direction' => CashDirection::Dong->value,
            'amount_expected' => 900_000,
        ])
        ->assertNoContent();
});
