<?php

declare(strict_types=1);

use App\Domain\HuiCalculator;

/*
 * Ví dụ kiểm thử ở document/01-dac-ta.md mục 5 (đánh số #1..#7).
 */

test('payout follows the specification example (#1)', function () {
    expect(HuiCalculator::payout(roundNo: 3, totalSlots: 10, faceValue: 1_000_000, bidAmount: 100_000))
        ->toBe(8_300_000);
});

test('net payout subtracts the fixed commission (#2, #3)', function (int $commission, int $expected) {
    expect(HuiCalculator::netPayout(roundNo: 3, totalSlots: 10, faceValue: 1_000_000, bidAmount: 100_000, commission: $commission))
        ->toBe($expected);
})->with([
    'no commission' => [0, 8_300_000],
    'commission 200.000 (#2)' => [200_000, 8_100_000],
    'commission 1.000.000 (#3)' => [1_000_000, 7_300_000],
]);

test('payout without bid is (N − 1) × M (#4)', function (int $roundNo) {
    expect(HuiCalculator::payout(roundNo: $roundNo, totalSlots: 10, faceValue: 1_000_000, bidAmount: 0))
        ->toBe(9_000_000);
})->with(['first round' => 1, 'middle round' => 5, 'last round' => 10]);

test('payout in the last round ignores the bid', function () {
    expect(HuiCalculator::payout(roundNo: 10, totalSlots: 10, faceValue: 1_000_000, bidAmount: 300_000))
        ->toBe(9_000_000);
});

test('amount due depends on whether the slot has won (#5)', function (?int $wonRoundNo, int $roundNo, int $expected) {
    expect(HuiCalculator::amountDue(roundNo: $roundNo, faceValue: 1_000_000, bidAmount: 100_000, wonRoundNo: $wonRoundNo))
        ->toBe($expected);
})->with([
    'not won yet pays M − L' => [null, 3, 900_000],
    'wins later pays M − L' => [7, 3, 900_000],
    'won earlier pays M' => [2, 5, 1_000_000],
    'wins this round pays nothing' => [3, 3, 0],
]);

test('profit is received minus paid, negative for a broken group (#7)', function () {
    expect(HuiCalculator::profit(totalReceived: 0, totalPaid: 5 * 900_000))->toBe(-4_500_000)
        ->and(HuiCalculator::profit(totalReceived: 8_300_000, totalPaid: 8_000_000))->toBe(300_000);
});

test('average bid only counts opened rounds and rounds to the nearest dong', function (array $bids, int $expected) {
    expect(HuiCalculator::averageBid($bids))->toBe($expected);
})->with([
    'no opened round' => [[1 => null, 2 => null], 0],
    'ignores unopened rounds' => [[1 => 0, 2 => 200_000, 3 => null], 100_000],
    'rounds half up' => [[1 => 1, 2 => 2], 2],
]);

test('projected profit fills unopened rounds with the average bid', function (int $winRoundNo, int $commission, int $expected) {
    $bids = [1 => 0, 2 => 200_000, 3 => null, 4 => null];

    expect(HuiCalculator::projectedProfit(totalSlots: 4, faceValue: 1_000_000, commission: $commission, bidsByRound: $bids, winRoundNo: $winRoundNo))
        ->toBe($expected);
})->with([
    // Đóng 1.000.000 + 800.000 + 900.000, nhận 3.000.000.
    'wins the last round' => [4, 0, 300_000],
    'wins the last round with commission' => [4, 100_000, 200_000],
    // Đóng 1.000.000 + 1.000.000 + 1.000.000, nhận 2.600.000.
    'won an opened round' => [2, 0, -400_000],
]);

test('unwon slots are assumed to win the last free rounds', function () {
    expect(HuiCalculator::assumedWinRounds(totalSlots: 10, takenRoundNos: [1, 2, 10], unwonSlotCount: 2))
        ->toBe([9, 8]);
});

test('invalid input is rejected', function (Closure $calculate, string $message) {
    expect($calculate)->toThrow(InvalidArgumentException::class, $message);
})->with([
    'round above N' => [fn () => HuiCalculator::payout(11, 10, 1_000_000, 0), 'Kỳ 11 nằm ngoài 1..10.'],
    'round zero' => [fn () => HuiCalculator::payout(0, 10, 1_000_000, 0), 'Kỳ 0 nằm ngoài 1..10.'],
    'single slot' => [fn () => HuiCalculator::payout(1, 1, 1_000_000, 0), 'Dây hụi phải có ít nhất 2 tay.'],
    'zero face value' => [fn () => HuiCalculator::amountDue(1, 0, 0, null), 'Mệnh giá phải lớn hơn 0.'],
    'bid above face value' => [fn () => HuiCalculator::amountDue(1, 1_000_000, 1_000_001, null), 'Lãi thăm phải từ 0 đến mệnh giá.'],
    'negative bid' => [fn () => HuiCalculator::amountDue(1, 1_000_000, -1, null), 'Lãi thăm phải từ 0 đến mệnh giá.'],
    'negative commission' => [fn () => HuiCalculator::netPayout(3, 10, 1_000_000, 0, -1), 'Tiền thảo không được âm.'],
    'commission above payout' => [fn () => HuiCalculator::netPayout(1, 2, 1_000_000, 1_000_000, 1), 'Tiền thảo lớn hơn số tiền hốt được.'],
    'more unwon slots than free rounds' => [fn () => HuiCalculator::assumedWinRounds(3, [1, 2], 2), 'Số tay chưa hốt nhiều hơn số kỳ còn trống.'],
]);
