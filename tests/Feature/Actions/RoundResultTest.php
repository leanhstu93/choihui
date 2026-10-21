<?php

declare(strict_types=1);

use App\Actions\Payments\MarkPaid;
use App\Actions\Rounds\RecordRoundResult;
use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\WinnerType;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\HuiActionException;
use App\Models\CashEntry;
use App\Models\HuiRound;
use App\Models\User;

/**
 * @return array<string, array{direction: string, expected: int}> nhãn tay => dòng tiền của kỳ
 */
function entriesOfRound(HuiRound $round): array
{
    return $round->cashEntries()->with('slot')->get()
        ->mapWithKeys(fn (CashEntry $entry) => [$entry->slot->label => ['direction' => $entry->direction->value, 'expected' => $entry->amount_expected]])
        ->sortKeys()
        ->all();
}

test('when someone else wins, each of my slots pays M − L', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup([], ['Tay 1', 'Tay 2']);

    app(RecordRoundResult::class)->handle($group->rounds[2], 100_000, WinnerType::NguoiKhac, winnerName: 'Chị Lan');

    expect(entriesOfRound($group->rounds[2]))->toBe([
        'Tay 1' => ['direction' => 'dong', 'expected' => 900_000],
        'Tay 2' => ['direction' => 'dong', 'expected' => 900_000],
    ])->and($group->rounds[2]->refresh()->winner_name)->toBe('Chị Lan');
});

test('when my slot wins, it receives the payout minus commission and my other slot pays', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['commission_amount' => 1_000_000], ['Tay 1', 'Tay 2']);

    app(RecordRoundResult::class)->handle($group->rounds[2], 100_000, WinnerType::Minh, $group->slots[0]);

    expect(entriesOfRound($group->rounds[2]))->toBe([
        'Tay 1' => ['direction' => 'nhan', 'expected' => 7_300_000],
        'Tay 2' => ['direction' => 'dong', 'expected' => 900_000],
    ]);
});

test('a slot that has won pays the full face value in later rounds', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);

    app(RecordRoundResult::class)->handle($group->rounds[1], 150_000, WinnerType::NguoiKhac);

    expect(entriesOfRound($group->rounds[1]))->toBe(['Tay 1' => ['direction' => 'dong', 'expected' => 1_000_000]]);
});

test('changing an earlier winner recalculates pending later payments', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::NguoiKhac);
    app(RecordRoundResult::class)->handle($group->rounds[1], 150_000, WinnerType::NguoiKhac);

    app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);

    expect(entriesOfRound($group->rounds[0]))->toBe(['Tay 1' => ['direction' => 'nhan', 'expected' => 9_000_000]])
        ->and(entriesOfRound($group->rounds[1]))->toBe(['Tay 1' => ['direction' => 'dong', 'expected' => 1_000_000]]);
});

test('changing the bid of a round that was already paid needs confirmation', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup(['due_offset_days' => 5]);
    $round = $group->rounds[0];
    app(RecordRoundResult::class)->handle($round, 100_000, WinnerType::NguoiKhac);
    $entry = $round->cashEntries()->sole();
    app(MarkPaid::class)->handle($entry, 900_000, new DateTimeImmutable('2026-01-06'));

    expect(fn () => app(RecordRoundResult::class)->handle($round, 50_000, WinnerType::NguoiKhac))
        ->toThrow(ConfirmationRequiredException::class, 'Thay đổi này ảnh hưởng 1 khoản đã ghi nhận tiền.')
        ->and($round->refresh()->bid_amount)->toBe(100_000);

    app(RecordRoundResult::class)->handle($round, 50_000, WinnerType::NguoiKhac, confirmed: true);

    expect($entry->refresh()->amount_expected)->toBe(950_000)
        ->and($entry->amount_actual)->toBe(900_000)
        ->and($entry->status)->toBe(CashEntryStatus::Thieu);
});

test('clearing a round result removes its pending entries', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();
    app(RecordRoundResult::class)->handle($group->rounds[0], 100_000, WinnerType::NguoiKhac);

    app(RecordRoundResult::class)->handle($group->rounds[0], null, WinnerType::ChuaCo);

    expect(CashEntry::count())->toBe(0);
});

test('invalid round results are rejected', function (Closure $record, string $message) {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup();

    expect(fn () => $record($group))->toThrow(HuiActionException::class, $message);
})->with([
    'winner without bid' => [fn ($group) => app(RecordRoundResult::class)->handle($group->rounds[0], null, WinnerType::NguoiKhac), 'Cần nhập lãi thăm trước khi chọn người hốt.'],
    'my win without slot' => [fn ($group) => app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh), 'Tay hốt không thuộc dây này.'],
    'slot from another group' => [fn ($group) => app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, createHuiGroup()->slots[0]), 'Tay hốt không thuộc dây này.'],
    'slot already won' => [function ($group) {
        app(RecordRoundResult::class)->handle($group->rounds[0], 0, WinnerType::Minh, $group->slots[0]);
        app(RecordRoundResult::class)->handle($group->rounds[1], 0, WinnerType::Minh, $group->slots[0]);
    }, 'Tay 1 đã hốt ở kỳ 1.'],
]);

test('entries record the payout direction only for the winning slot', function () {
    $this->actingAs(User::factory()->create());
    $group = createHuiGroup([], ['Tay 1', 'Tay 2', 'Tay 3']);

    app(RecordRoundResult::class)->handle($group->rounds[4], 200_000, WinnerType::Minh, $group->slots[1]);

    expect(CashEntry::query()->where('direction', CashDirection::Nhan)->sole()->amount_expected)
        ->toBe(4 * 1_000_000 + 5 * 800_000);
});
