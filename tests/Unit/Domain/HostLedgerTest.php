<?php

declare(strict_types=1);

use App\Domain\HostLedger;

/*
 * Ví dụ kiểm thử ở document/04-chu-hui.md mục 5 (V1..V3).
 */

/**
 * Dây 10 tay, tay 1 và 2 đã hốt kỳ 1 và 2, các tay còn lại chưa hốt.
 *
 * @return array<int, int|null>
 */
function tenSlotsAfterTwoRounds(): array
{
    return [1 => 1, 2 => 2] + array_fill(3, 8, null);
}

test('a round collects from every other slot and pays the winner minus the commission (V1)', function () {
    $entries = HostLedger::roundEntries(roundNo: 3, totalSlots: 10, faceValue: 1_000_000, bidAmount: 100_000, commission: 200_000, wonRoundBySlot: tenSlotsAfterTwoRounds(), winnerSlotNo: 5);

    expect($entries['collections'])->toHaveCount(9)
        ->and($entries['collections'][1])->toBe(1_000_000)
        ->and($entries['collections'][3])->toBe(900_000)
        ->and(array_sum($entries['collections']))->toBe(8_300_000)
        ->and($entries['payout'])->toBe(8_100_000)
        // Mọi tay đóng đủ thì chủ hụi giữ đúng tiền thảo.
        ->and(array_sum($entries['collections']) - $entries['payout'])->toBe(200_000);
});

test('the host tops up the payout when a slot has not paid and that member owes it (V2)', function () {
    $entries = HostLedger::roundEntries(3, 10, 1_000_000, 100_000, 200_000, tenSlotsAfterTwoRounds(), 5);
    $collected = array_sum($entries['collections']) - $entries['collections'][3];

    expect($collected)->toBe(7_400_000)
        ->and(HostLedger::topUp($collected, $entries['payout']))->toBe(700_000)
        ->and(HostLedger::debt([['expected' => $entries['collections'][3], 'actual' => 0]]))->toBe(900_000);
});

test('the first round collects the full face value from every other slot (V3)', function () {
    $entries = HostLedger::roundEntries(1, 10, 1_000_000, 0, 200_000, array_fill(1, 10, null), 1);

    expect(array_sum($entries['collections']))->toBe(9_000_000)
        ->and($entries['payout'])->toBe(8_800_000);
});

test('re-entering the result of a round keeps its own winner valid', function () {
    $entries = HostLedger::roundEntries(3, 10, 1_000_000, 100_000, 0, [3 => 3] + tenSlotsAfterTwoRounds(), 3);

    expect($entries['collections'])->not->toHaveKey(3)
        ->and($entries['payout'])->toBe(8_300_000);
});

test('there is no top-up when enough has been collected', function () {
    expect(HostLedger::topUp(collected: 8_300_000, paidOut: 8_100_000))->toBe(0);
});

test('debt adds up the shortfall of each entry without netting overpayments', function () {
    expect(HostLedger::debt([
        ['expected' => 900_000, 'actual' => 500_000],
        ['expected' => 1_000_000, 'actual' => 1_200_000],
        ['expected' => 900_000, 'actual' => 900_000],
    ]))->toBe(400_000);
});

test('host profit adds collectible debt and future commissions to the projection', function () {
    expect(HostLedger::hostProfit(collected: 7_400_000, paidOut: 8_100_000, collectibleDebt: 900_000, commission: 200_000, unopenedRounds: 7))
        ->toBe(['actual' => -700_000, 'projected' => 1_600_000]);
});

test('invalid round input is rejected', function (Closure $calculate, string $message) {
    expect($calculate)->toThrow(InvalidArgumentException::class, $message);
})->with([
    'missing slots' => [fn () => HostLedger::roundEntries(3, 10, 1_000_000, 0, 0, [1 => null, 2 => null], 1), 'Dây phải có đủ 10 tay.'],
    'unknown winner' => [fn () => HostLedger::roundEntries(3, 10, 1_000_000, 0, 0, tenSlotsAfterTwoRounds(), 11), 'Tay 11 không thuộc dây.'],
    'winner already won' => [fn () => HostLedger::roundEntries(3, 10, 1_000_000, 0, 0, tenSlotsAfterTwoRounds(), 2), 'Tay 2 đã hốt ở kỳ 2.'],
    'bid above face value' => [fn () => HostLedger::roundEntries(3, 10, 1_000_000, 1_000_001, 0, tenSlotsAfterTwoRounds(), 5), 'Lãi thăm phải từ 0 đến mệnh giá.'],
]);
