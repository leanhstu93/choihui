<?php

declare(strict_types=1);

use App\Actions\Groups\AddSlot;
use App\Actions\Groups\DeleteGroup;
use App\Actions\Groups\FinishGroup;
use App\Actions\Groups\RemoveSlot;
use App\Actions\Groups\UpdateGroup;
use App\Actions\Payments\MarkPaid;
use App\Actions\Payments\RecordReceipt;
use App\Actions\Rounds\RecordRoundResult;
use App\Enums\CashDirection;
use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\HuiActionException;
use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\User;
use Illuminate\Database\QueryException;

test('creating a group generates its slots and monthly rounds', function () {
    $this->actingAs($user = User::factory()->create());

    $group = createHuiGroup(['total_slots' => 4, 'start_date' => '2026-01-31'], ['Tay 1', 'Tay 2']);

    expect($group->user_id)->toBe($user->id)
        ->and($group->slots->pluck('label')->all())->toBe(['Tay 1', 'Tay 2'])
        ->and($group->rounds->map(fn (HuiRound $round) => $round->open_date->format('d/m/Y'))->all())
        ->toBe(['31/01/2026', '28/02/2026', '31/03/2026', '30/04/2026'])
        ->and(CashEntry::count())->toBe(0);
});

test('creating a group with more slots than the group has stores nothing', function () {
    $this->actingAs(User::factory()->create());

    expect(fn () => createHuiGroup(['total_slots' => 2], ['Tay 1', 'Tay 2', 'Tay 3']))
        ->toThrow(HuiActionException::class, 'Số tay của mình không được vượt quá tổng số tay của dây.')
        ->and(HuiGroup::count())->toBe(0);
});

test('a failure while creating rolls back the whole group', function () {
    $this->actingAs(User::factory()->create());

    expect(fn () => createHuiGroup([], ['Tay 1', 'Tay 1']))->toThrow(QueryException::class)
        ->and(HuiGroup::count())->toBe(0)
        ->and(HuiRound::count())->toBe(0);
});

test('deleting a group removes its rounds, slots and cash entries even after a win', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);

    app(DeleteGroup::class)->handle($group);

    expect(HuiGroup::count())->toBe(0)
        ->and(MySlot::count())->toBe(0)
        ->and(HuiRound::count())->toBe(0)
        ->and(CashEntry::count())->toBe(0);
});

test('deleting a group leaves other groups untouched', function () {
    $this->actingAs(User::factory()->create());
    $keptGroup = createHuiGroup(['name' => 'Giữ lại']);
    app(RecordRoundResult::class)->handle($keptGroup->rounds[0], 100_000, WinnerType::NguoiKhac);

    app(DeleteGroup::class)->handle(createHuiGroup());

    expect(HuiGroup::pluck('id')->all())->toBe([$keptGroup->id])
        ->and(HuiRound::count())->toBe(10)
        ->and(CashEntry::count())->toBe(1);
});

test('a new slot gets payments for rounds that already have a result', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);

    $slot = app(AddSlot::class)->handle($group, 'Tay 2');

    expect($slot->cashEntries()->sole()->amount_expected)->toBe(900_000);
});

test('slots cannot exceed the group size or repeat a label', function (string $label, string $message) {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['total_slots' => 2], ['Tay 1']);
    if ($label === 'Tay 3') {
        app(AddSlot::class)->handle($group, 'Tay 2');
    }

    expect(fn () => app(AddSlot::class)->handle($group, $label))->toThrow(HuiActionException::class, $message);
})->with([
    'group is full' => ['Tay 3', 'Số tay của mình không được vượt quá tổng số tay của dây.'],
    'duplicate label' => ['Tay 1', 'Tên tay đã có trong dây này.'],
]);

test('a slot without history can be removed with its pending entries', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup([], ['Tay 1', 'Tay 2']);
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);

    app(RemoveSlot::class)->handle($group->slots[1]);

    expect(MySlot::pluck('label')->all())->toBe(['Tay 1'])
        ->and(CashEntry::count())->toBe(1);
});

test('a slot that won or has payments cannot be removed', function (bool $won, string $message) {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    $slot = $group->slots[0];

    if ($won) {
        app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $slot);
    } else {
        app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);
        app(MarkPaid::class)->handle($slot->cashEntries()->sole(), 900_000, new DateTimeImmutable('2026-01-05'));
    }

    expect(fn () => app(RemoveSlot::class)->handle($slot))->toThrow(HuiActionException::class, $message);
})->with([
    'won' => [true, 'Tay này đã hốt nên không thể xóa.'],
    'paid' => [false, 'Tay này đã có khoản đóng tiền nên không thể xóa.'],
]);

test('changing the commission updates the pending payout', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[2], 100_000, WinnerType::Minh, $group->slots[0]);

    app(UpdateGroup::class)->handle($group, ['commission_amount' => 1_000_000]);

    expect(CashEntry::query()->where('direction', CashDirection::Nhan)->sole()->amount_expected)->toBe(7_300_000);
});

test('changing the commission after receiving the payout needs confirmation', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[2], 100_000, WinnerType::Minh, $group->slots[0]);
    $payout = CashEntry::query()->where('direction', CashDirection::Nhan)->sole();
    app(RecordReceipt::class)->handle($payout, 8_300_000, new DateTimeImmutable('2026-03-05'));

    expect(fn () => app(UpdateGroup::class)->handle($group, ['commission_amount' => 1_000_000]))
        ->toThrow(ConfirmationRequiredException::class)
        ->and($group->refresh()->commission_amount)->toBe(0);

    app(UpdateGroup::class)->handle($group, ['commission_amount' => 1_000_000], confirmed: true);

    expect($payout->refresh()->amount_expected)->toBe(7_300_000)
        ->and($payout->status->value)->toBe('du');
});

test('a group finishes only when every round has a result', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['total_slots' => 2]);
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::NguoiKhac);

    expect(fn () => app(FinishGroup::class)->handle($group))
        ->toThrow(HuiActionException::class, 'Còn 1 kỳ chưa nhập kết quả.');

    app(RecordRoundResult::class)->handle($group->rounds[1], 0, WinnerType::Minh, $group->slots[0]);

    expect(app(FinishGroup::class)->handle($group)->status)->toBe(HuiGroupStatus::KetThuc);
});
