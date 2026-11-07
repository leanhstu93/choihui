<?php

declare(strict_types=1);

use App\Domain\RepairLedger;

/*
 * Quy tắc độ bền sổ sửa chữa (document/08-sua-chua.md mục 4).
 */

test('part names match regardless of case and extra spaces', function () {
    expect(RepairLedger::partKey('  Lốp   SAU '))->toBe(RepairLedger::partKey('lốp sau'))
        ->and(RepairLedger::partKey('Lốp sau'))->not->toBe(RepairLedger::partKey('Lốp trước'));
});

test('durations read in years, months or days', function (string $from, string $to, ?string $expected) {
    expect(RepairLedger::duration(new DateTimeImmutable($from), new DateTimeImmutable($to)))->toBe($expected);
})->with([
    'years and months' => ['2024-02-10', '2026-05-10', '2 năm 3 tháng'],
    'whole years' => ['2024-02-10', '2026-02-10', '2 năm'],
    'days are dropped once there are months' => ['2026-01-10', '2026-06-25', '5 tháng'],
    'just under a month' => ['2026-01-10', '2026-02-09', '30 ngày'],
    'same day has no duration' => ['2026-01-10', '2026-01-10', null],
    'reversed dates have no duration' => ['2026-01-11', '2026-01-10', null],
]);

test('distance needs two readings that go up', function (?int $from, ?int $to, ?int $expected) {
    expect(RepairLedger::distance($from, $to))->toBe($expected);
})->with([
    [10_000, 22_500, 12_500],
    [0, 500, 500],
    [null, 500, null],
    [500, null, null],
    [500, 500, null],
    [900, 500, null],
]);
