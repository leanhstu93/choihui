<?php

declare(strict_types=1);

use App\Actions\Groups\MarkGroupBroken;
use App\Actions\Groups\UndoGroupBroken;
use App\Actions\Payments\MarkPaid;
use App\Actions\Payments\RecordReceipt;
use App\Actions\Rounds\RecordRoundResult;
use App\Domain\HuiCalculator;
use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\WinnerType;
use App\Exceptions\HuiActionException;
use App\Models\CashEntry;
use App\Models\User;

test('marking a payment sets its status from the amount and due date', function (int $amount, ?string $paidDate, CashEntryStatus $expected) {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['start_date' => '2026-01-05', 'due_offset_days' => 2]);
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);

    $entry = app(MarkPaid::class)->handle($group->rounds[0]->cashEntries()->sole(), $amount, $paidDate === null ? null : new DateTimeImmutable($paidDate));

    expect($entry->status)->toBe($expected);
})->with([
    'on time' => [900_000, '2026-01-07', CashEntryStatus::Du],
    'late' => [900_000, '2026-01-08', CashEntryStatus::Muon],
    'short' => [400_000, '2026-01-06', CashEntryStatus::Thieu],
    'unmarked' => [0, null, CashEntryStatus::Chua],
]);

test('receipts record shortfalls as owed or written off', function (bool $writtenOff, CashEntryStatus $expected) {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[2], 100_000, WinnerType::Minh, $group->slots[0]);

    $entry = app(RecordReceipt::class)->handle($group->rounds[2]->cashEntries()->sole(), 6_000_000, new DateTimeImmutable('2026-03-05'), $writtenOff);

    expect($entry->status)->toBe($expected)
        ->and($entry->amount_actual)->toBe(6_000_000);
})->with([
    'still owed' => [false, CashEntryStatus::ConNo],
    'written off' => [true, CashEntryStatus::Bung],
]);

test('payments and receipts cannot be recorded on the wrong kind of entry', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup([], ['Tay 1', 'Tay 2']);
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);
    $payout = CashEntry::query()->where('direction', CashDirection::Nhan)->sole();
    $payment = CashEntry::query()->where('direction', CashDirection::Dong)->sole();

    expect(fn () => app(MarkPaid::class)->handle($payout, 1, new DateTimeImmutable))
        ->toThrow(HuiActionException::class, 'Đây là khoản nhận, hãy dùng ghi nhận tiền nhận.')
        ->and(fn () => app(RecordReceipt::class)->handle($payment, 1, new DateTimeImmutable))
        ->toThrow(HuiActionException::class, 'Đây là khoản đóng, hãy dùng đánh dấu đã đóng.');
});

test('a broken group stops pending payments and keeps the loss (#7)', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    foreach (range(0, 5) as $index) {
        app(RecordRoundResult::class)->handle($group->rounds[$index], 100_000, WinnerType::NguoiKhac);
    }
    foreach (range(0, 4) as $index) {
        app(MarkPaid::class)->handle($group->rounds[$index]->cashEntries()->sole(), 900_000, new DateTimeImmutable('2026-01-05'));
    }

    app(MarkGroupBroken::class)->handle($group, 6);

    expect(CashEntry::query()->where('status', CashEntryStatus::Chua)->count())->toBe(0)
        ->and(HuiCalculator::profit((int) CashEntry::query()->where('direction', CashDirection::Nhan)->sum('amount_actual'), (int) CashEntry::query()->sum('amount_actual')))
        ->toBe(-4_500_000)
        ->and(fn () => app(RecordRoundResult::class)->handle($group->rounds[6], 0, WinnerType::NguoiKhac))
        ->toThrow(HuiActionException::class, 'Dây đã bể từ kỳ 6, không nhập kết quả cho kỳ sau đó.');
});

test('the payout of the broken round is kept so a shortfall can be recorded', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup([], ['Tay 1', 'Tay 2']);
    app(RecordRoundResult::class)->handle($group->rounds[2], 100_000, WinnerType::Minh, $group->slots[0]);

    app(MarkGroupBroken::class)->handle($group, 3);

    expect(CashEntry::query()->with('slot')->get()->map(fn (CashEntry $entry) => $entry->slot->label.':'.$entry->direction->value)->all())
        ->toBe(['Tay 1:nhan']);
});

test('undoing a break regenerates the pending payments', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);
    app(MarkGroupBroken::class)->handle($group, 1);

    app(UndoGroupBroken::class)->handle($group);

    expect($group->rounds[0]->cashEntries()->sole()->amount_expected)->toBe(900_000);
});

test('the broken round must be within the group', function () {
    $this->actingAs(User::factory()->create());

    expect(fn () => app(MarkGroupBroken::class)->handle(createHuiGroup(), 11))
        ->toThrow(HuiActionException::class, 'Kỳ bể phải từ 1 đến 10.');
});
