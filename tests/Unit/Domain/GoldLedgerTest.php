<?php

declare(strict_types=1);

use App\Domain\GoldLedger;

/*
 * Công thức sổ vàng (document/06-vang.md mục 4).
 */

test('the worked example of section 4 adds up', function () {
    expect(GoldLedger::remainingWeight(200, 50))->toBe(150)
        ->and(GoldLedger::soldCost(30_000_000, 200, 50))->toBe(7_500_000)
        ->and(GoldLedger::remainingCost(30_000_000, 200, 50))->toBe(22_500_000)
        ->and(GoldLedger::value(150, 15_500_000))->toBe(23_250_000)
        ->and(GoldLedger::unrealizedProfit(30_000_000, 200, 50, 15_500_000))->toBe(750_000)
        ->and(GoldLedger::realizedProfit(30_000_000, 200, 50, 8_000_000))->toBe(500_000);
});

test('sold cost and remaining cost always add back to the full cost', function (int $cost, int $weight, int $sold) {
    expect(GoldLedger::soldCost($cost, $weight, $sold) + GoldLedger::remainingCost($cost, $weight, $sold))->toBe($cost);
})->with([
    [10_000_001, 3, 1],
    [10_000_001, 2, 1],
    [10_000_000, 7, 3],
    [1, 3, 2],
    [0, 100, 40],
]);

test('rounding goes to the nearest dong', function () {
    expect(GoldLedger::soldCost(100, 3, 1))->toBe(33)
        ->and(GoldLedger::soldCost(100, 3, 2))->toBe(67)
        ->and(GoldLedger::value(1, 150))->toBe(2);
});

test('the largest allowed values do not overflow', function () {
    expect(GoldLedger::soldCost(1_000_000_000_000, 1_000_000, 999_999))->toBe(999_999_000_000)
        ->and(GoldLedger::value(1_000_000, 10_000_000_000))->toBe(100_000_000_000_000);
});

test('gold given as a gift has no cost, so all of its sale is profit', function () {
    expect(GoldLedger::realizedProfit(0, 100, 100, 15_000_000))->toBe(15_000_000);
});

test('chi input accepts both decimal separators', function (string $input, ?int $expected) {
    expect(GoldLedger::parseChi($input))->toBe($expected);
})->with([
    ['2', 200],
    ['1,25', 125],
    ['1.25', 125],
    ['0,5', 50],
    [' 0.05 ', 5],
    ['', null],
    ['abc', null],
    ['1,255', null],
    ['-1', null],
    ['1.000,5', null],
]);

test('chi output drops trailing zeros', function (int $weight, string $expected) {
    expect(GoldLedger::formatChi($weight))->toBe($expected);
})->with([
    [125, '1,25'],
    [250, '2,5'],
    [300, '3'],
    [5, '0,05'],
    [50, '0,5'],
    [120000, '1.200'],
]);
