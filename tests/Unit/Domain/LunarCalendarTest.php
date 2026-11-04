<?php

declare(strict_types=1);

use App\Domain\LunarCalendar;
use App\Domain\LunarDate;

test('lunar new year falls on the published Vietnamese dates', function (int $lunarYear, string $solar) {
    expect(LunarCalendar::toSolar(new LunarDate(1, 1, $lunarYear))->format('Y-m-d'))->toBe($solar);

    $lunar = LunarCalendar::fromSolar(new DateTimeImmutable($solar));

    expect([$lunar->day, $lunar->month, $lunar->year, $lunar->isLeap])->toBe([1, 1, $lunarYear, false]);
})->with([
    '2024' => [2024, '2024-02-10'],
    '2025' => [2025, '2025-01-29'],
    '2026' => [2026, '2026-02-17'],
    '2027' => [2027, '2027-02-06'],
    '2028' => [2028, '2028-01-26'],
    'Việt Nam khác Trung Quốc năm 2007' => [2007, '2007-02-17'],
    'Việt Nam khác Trung Quốc năm 1985' => [1985, '1985-01-21'],
]);

test('leap months match the published calendar', function (int $year, ?int $leapMonth) {
    expect(LunarCalendar::leapMonth($year))->toBe($leapMonth);
})->with([
    [1990, 5], [1993, 3], [1995, 8], [1998, 5], [2001, 4], [2004, 2], [2006, 7], [2009, 5], [2012, 4],
    [2014, 9], [2017, 6], [2020, 4], [2023, 2], [2025, 6], [2028, 5], [2031, 3], [2033, 11], [2036, 6],
    [2039, 5],
    [2024, null], [2026, null], [2027, null], [2034, null],
]);

test('leap month starts on the day after its true new moon boundary', function () {
    // Sóc 21/03/2023 17:23 UT là 00:23 ngày 22/03 giờ Việt Nam: sát ranh giới ngày.
    $lunar = LunarCalendar::fromSolar(new DateTimeImmutable('2023-03-22'));

    expect([$lunar->day, $lunar->month, $lunar->isLeap])->toBe([1, 2, true])
        ->and(LunarCalendar::fromSolar(new DateTimeImmutable('2023-03-21'))->month)->toBe(2)
        ->and(LunarCalendar::fromSolar(new DateTimeImmutable('2023-03-21'))->isLeap)->toBeFalse();
});

test('every solar day round-trips through the lunar calendar', function () {
    $date = new DateTimeImmutable('2019-01-01');

    while ($date->format('Y') < '2040') {
        $lunar = LunarCalendar::fromSolar($date);

        expect(LunarCalendar::toSolar($lunar)->format('Y-m-d'))->toBe($date->format('Y-m-d'));

        $date = $date->modify('+1 day');
    }
});

test('labels mark a leap month', function () {
    expect((new LunarDate(15, 10, 2026))->label())->toBe('15/10 ÂL')
        ->and((new LunarDate(3, 5, 2028, true))->label())->toBe('3/5N ÂL');
});

test('dates that do not exist are rejected', function (LunarDate $date, string $message) {
    expect(fn () => LunarCalendar::toSolar($date))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'leap month in a normal year' => [new LunarDate(1, 5, 2026, true), 'Năm 2026 không có tháng 5 nhuận.'],
    'month 13' => [new LunarDate(1, 13, 2026), 'Ngày âm lịch không hợp lệ.'],
]);
