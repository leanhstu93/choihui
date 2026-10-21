<?php

declare(strict_types=1);

use App\Domain\HuiSchedule;

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
