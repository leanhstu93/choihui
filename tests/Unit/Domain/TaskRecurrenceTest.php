<?php

declare(strict_types=1);

use App\Domain\TaskRecurrence;
use App\Enums\TaskRepeat;

/*
 * Lần tới của việc lặp lại (document/10-viec-can-lam.md mục 4).
 */

const MON = 0b0000001;
const THU = 0b0001000;

function nextTask(TaskRepeat $repeat, string $due, string $today, int $weekdays = 0, ?int $anchor = null): string
{
    return TaskRecurrence::next($repeat, new DateTimeImmutable($due), new DateTimeImmutable($today), $weekdays, $anchor)->format('Y-m-d');
}

test('spec examples 1 to 8', function (TaskRepeat $repeat, string $due, string $today, int $weekdays, ?int $anchor, string $expected) {
    expect(nextTask($repeat, $due, $today, $weekdays, $anchor))->toBe($expected);
})->with([
    '1 · late daily task skips the missed days' => [TaskRepeat::Daily, '2026-11-16', '2026-11-18', 0, null, '2026-11-19'],
    '2 · weekly mon+thu done late' => [TaskRepeat::Weekly, '2026-11-16', '2026-11-18', MON | THU, null, '2026-11-19'],
    '3 · weekly mon+thu done early keeps the due date' => [TaskRepeat::Weekly, '2026-11-16', '2026-11-15', MON | THU, null, '2026-11-19'],
    '4 · monthly day 31 in February' => [TaskRepeat::Monthly, '2027-01-31', '2027-01-31', 0, 31, '2027-02-28'],
    '4 · monthly day 31 returns to the anchor' => [TaskRepeat::Monthly, '2027-02-28', '2027-02-28', 0, 31, '2027-03-31'],
    '5 · full moon of month 10 to month 11' => [TaskRepeat::MonthlyLunar, '2026-11-23', '2026-11-23', 0, 15, '2026-12-23'],
    '6 · lunar day 30 in a 29-day month' => [TaskRepeat::MonthlyLunar, '2027-01-07', '2027-01-07', 0, 30, '2027-02-05'],
    '7 · the leap month counts' => [TaskRepeat::MonthlyLunar, '2025-07-24', '2025-07-24', 0, 30, '2025-08-22'],
    '8 · 29 February yearly' => [TaskRepeat::Yearly, '2028-02-29', '2028-02-29', 0, 29, '2029-02-28'],
]);

test('weekly without chosen days repeats on the weekday of the due date', function () {
    expect(nextTask(TaskRepeat::Weekly, '2026-11-18', '2026-11-18'))->toBe('2026-11-25');
});

test('anchor days default to the due date', function () {
    expect(nextTask(TaskRepeat::Monthly, '2026-11-18', '2026-11-18'))->toBe('2026-12-18')
        ->and(nextTask(TaskRepeat::MonthlyLunar, '2026-11-18', '2026-11-18'))->toBe('2026-12-18')
        ->and(nextTask(TaskRepeat::Yearly, '2026-11-18', '2026-11-18'))->toBe('2027-11-18');
});

test('yearly 29 February comes back in a leap year', function () {
    expect(nextTask(TaskRepeat::Yearly, '2031-02-28', '2031-02-28', 0, 29))->toBe('2032-02-29');
});

test('first occurrence on or after a date counts that date', function () {
    $from = new DateTimeImmutable('2026-11-23');

    expect(TaskRecurrence::firstOnOrAfter(TaskRepeat::MonthlyLunar, $from, anchorDay: 15)->format('Y-m-d'))->toBe('2026-11-23')
        ->and(TaskRecurrence::firstOnOrAfter(TaskRepeat::Weekly, $from, MON)->format('Y-m-d'))->toBe('2026-11-23')
        ->and(TaskRecurrence::firstOnOrAfter(TaskRepeat::Weekly, $from, THU)->format('Y-m-d'))->toBe('2026-11-26');
});

test('preview lists the next occurrences after the due date', function () {
    $dates = TaskRecurrence::preview(TaskRepeat::MonthlyLunar, new DateTimeImmutable('2026-11-24'), 3, anchorDay: 1);

    expect(array_map(fn (DateTimeImmutable $d): string => $d->format('Y-m-d'), $dates))
        ->toBe(['2026-12-09', '2027-01-08', '2027-02-06']);
});

test('a non-repeating task has no next occurrence', function () {
    nextTask(TaskRepeat::None, '2026-11-18', '2026-11-18');
})->throws(InvalidArgumentException::class);
