<?php

declare(strict_types=1);

use App\Domain\HuiSchedule;
use App\Domain\LunarCalendar;
use App\Domain\LunarDate;
use App\Enums\HuiFrequency;

test('open dates never drift after a short month (#6)', function () {
    $dates = HuiSchedule::openDates(new DateTimeImmutable('2026-01-31'), 4);

    expect(array_map(fn (DateTimeImmutable $date) => $date->format('d/m/Y'), $dates))->toBe([
        1 => '31/01/2026',
        2 => '28/02/2026',
        3 => '31/03/2026',
        4 => '30/04/2026',
    ]);
});

test('open date handles leap years and year boundaries', function (string $startDate, int $roundNo, string $expected) {
    expect(HuiSchedule::openDate(new DateTimeImmutable($startDate), $roundNo)->format('Y-m-d'))->toBe($expected);
})->with([
    'leap February' => ['2024-01-31', 2, '2024-02-29'],
    'crosses into next year' => ['2026-11-15', 3, '2027-01-15'],
    'twelve months later' => ['2026-11-05', 13, '2027-11-05'],
]);

test('due date adds the offset days to the open date', function () {
    expect(HuiSchedule::dueDate(new DateTimeImmutable('2026-02-27'), 3)->format('Y-m-d'))->toBe('2026-03-02')
        ->and(HuiSchedule::dueDate(new DateTimeImmutable('2026-02-27'), 0)->format('Y-m-d'))->toBe('2026-02-27');
});

test('invalid schedule input is rejected', function (Closure $calculate, string $message) {
    expect($calculate)->toThrow(InvalidArgumentException::class, $message);
})->with([
    'round zero' => [fn () => HuiSchedule::openDate(new DateTimeImmutable('2026-01-01'), 0), 'Số thứ tự kỳ phải từ 1 trở lên.'],
    'negative offset' => [fn () => HuiSchedule::dueDate(new DateTimeImmutable('2026-01-01'), -1), 'Số ngày hạn đóng không được âm.'],
]);

test('lunar open dates keep the same lunar day', function () {
    // 15/9 âm 2026 → rằm các tháng sau.
    $start = LunarCalendar::toSolar(new LunarDate(15, 9, 2026));
    $dates = HuiSchedule::openDates($start, 4, HuiFrequency::ThangAm);

    expect(array_map(fn (DateTimeImmutable $date) => LunarCalendar::fromSolar($date)->label(), $dates))->toBe([
        1 => '15/9 ÂL',
        2 => '15/10 ÂL',
        3 => '15/11 ÂL',
        4 => '15/12 ÂL',
    ]);
});

test('lunar schedule counts a leap month as a round', function () {
    $start = LunarCalendar::toSolar(new LunarDate(10, 4, 2028));
    $dates = HuiSchedule::openDates($start, 4, HuiFrequency::ThangAm);

    expect(array_map(fn (DateTimeImmutable $date) => LunarCalendar::fromSolar($date)->label(), $dates))->toBe([
        1 => '10/4 ÂL',
        2 => '10/5 ÂL',
        3 => '10/5N ÂL',
        4 => '10/6 ÂL',
    ]);
});

test('lunar day 30 falls back to 29 in short months without drifting', function () {
    $thirtieth = null;

    // Tìm tháng đủ có tháng thiếu liền sau để không phụ thuộc vào năm cụ thể.
    foreach (range(1, 11) as $month) {
        try {
            $candidate = LunarCalendar::toSolar(new LunarDate(30, $month, 2026));
        } catch (InvalidArgumentException) {
            continue;
        }

        try {
            LunarCalendar::toSolar(new LunarDate(30, $month + 1, 2026));
        } catch (InvalidArgumentException) {
            $thirtieth = $candidate;

            break;
        }
    }

    expect($thirtieth)->not->toBeNull();

    $days = array_map(
        fn (DateTimeImmutable $date) => LunarCalendar::fromSolar($date)->day,
        HuiSchedule::openDates($thirtieth, 2, HuiFrequency::ThangAm),
    );

    expect($days)->toBe([1 => 30, 2 => 29]);

    $start = LunarCalendar::fromSolar($thirtieth);
    $monthAfterShort = HuiSchedule::openDate($thirtieth, 3, HuiFrequency::ThangAm);
    $expectedDay = LunarCalendar::fromSolar($monthAfterShort->modify('+1 day'))->day === 1 ? 30 : 29;

    expect($start->day)->toBe(30)
        ->and(LunarCalendar::fromSolar($monthAfterShort)->day)->toBe($expectedDay);
});
