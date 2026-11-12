<?php

declare(strict_types=1);

use App\Domain\FamilyCalendar;
use App\Enums\FamilyEventKind;

/*
 * Ngày nhớ lặp lại hằng năm (document/09-gia-dinh.md mục 4).
 */

function nextOn(bool $lunar, int $day, int $month, string $from): string
{
    return FamilyCalendar::nextOccurrence($lunar, $day, $month, new DateTimeImmutable($from))->format('Y-m-d');
}

test('spec examples 1 to 7', function (bool $lunar, int $day, int $month, string $from, string $expected) {
    expect(nextOn($lunar, $day, $month, $from))->toBe($expected);
})->with([
    '1 · mid-autumn already passed' => [true, 15, 8, '2026-11-12', '2027-09-15'],
    '2 · next lunar new year' => [true, 1, 1, '2026-11-12', '2027-02-06'],
    '3 · month 12 still in the current lunar year' => [true, 10, 12, '2026-11-12', '2027-01-17'],
    '4 · day 30 of a short month falls back to 29' => [true, 30, 12, '2026-11-12', '2027-02-05'],
    '5 · the leap month is skipped' => [true, 15, 6, '2025-07-01', '2025-07-09'],
    '6 · 29 February in a common year' => [false, 29, 2, '2026-03-01', '2027-02-28'],
    '7 · today counts' => [false, 12, 11, '2026-11-12', '2026-11-12'],
]);

test('after the regular month the leap month is not used either', function () {
    // 15/6 thường năm 2025 là 09/07, 15/6 nhuận là 08/08: từ 15/07 phải sang năm 2026.
    expect(nextOn(true, 15, 6, '2025-07-15'))->toBe('2026-07-28');
});

test('a time of day does not push today into next year', function () {
    expect(FamilyCalendar::nextOccurrence(false, 12, 11, new DateTimeImmutable('2026-11-12 18:30'))->format('Y-m-d'))->toBe('2026-11-12');
});

test('29 February comes back in a leap year', function () {
    expect(nextOn(false, 29, 2, '2027-03-01'))->toBe('2028-02-29');
});

test('years since the origin year, lunar years for lunar dates', function () {
    // Ví dụ 8: sinh năm âm 1966, lần tới trong năm âm 2026 (17/01/2027 dương vẫn là năm âm 2026).
    $occurrence = FamilyCalendar::nextOccurrence(true, 10, 12, new DateTimeImmutable('2026-11-12'));
    expect(FamilyCalendar::yearsSince(true, 1966, $occurrence))->toBe(60)
        ->and(FamilyCalendar::yearsSince(false, 1966, $occurrence))->toBe(61)
        ->and(FamilyCalendar::yearsSince(true, null, $occurrence))->toBeNull()
        ->and(FamilyCalendar::yearsSince(true, 2026, $occurrence))->toBeNull()
        ->and(FamilyCalendar::yearsSince(true, 2030, $occurrence))->toBeNull();
});

test('years read by kind', function () {
    // Ví dụ 9: mất năm 2025, giỗ năm 2026 là giỗ đầu.
    expect(FamilyEventKind::Gio->yearsLabel(1))->toBe('Giỗ đầu')
        ->and(FamilyEventKind::Gio->yearsLabel(3))->toBe('Giỗ lần thứ 3')
        ->and(FamilyEventKind::SinhNhat->yearsLabel(60))->toBe('Tròn 60 tuổi')
        ->and(FamilyEventKind::KyNiem->yearsLabel(10))->toBe('10 năm');
});

test('days between dates', function () {
    $today = new DateTimeImmutable('2026-11-12 09:00');
    expect(FamilyCalendar::daysBetween($today, new DateTimeImmutable('2026-11-12')))->toBe(0)
        ->and(FamilyCalendar::daysBetween($today, new DateTimeImmutable('2026-11-15')))->toBe(3)
        ->and(FamilyCalendar::daysBetween($today, new DateTimeImmutable('2027-11-12')))->toBe(365)
        ->and(FamilyCalendar::daysBetween($today, new DateTimeImmutable('2026-11-10')))->toBe(-2);
});

test('impossible dates are rejected', function (bool $lunar, int $day, int $month) {
    expect(FamilyCalendar::isValid($lunar, $day, $month))->toBeFalse()
        ->and(fn () => FamilyCalendar::nextOccurrence($lunar, $day, $month, new DateTimeImmutable('2026-01-01')))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'lunar day 31' => [true, 31, 1],
    'solar 31 April' => [false, 31, 4],
    'solar 30 February' => [false, 30, 2],
    'month 13' => [false, 1, 13],
    'day 0' => [true, 0, 5],
]);
