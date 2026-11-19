<?php

declare(strict_types=1);

use App\Domain\ParsedTask;
use App\Domain\TaskQuickParse;
use App\Enums\TaskRepeat;

/*
 * Đọc dòng thêm nhanh (document/10-viec-can-lam.md V7, ví dụ 9–13). 18/11/2026 là thứ 4.
 */

function quick(string $input, string $now = '2026-11-18 09:00', array $ignore = []): ParsedTask
{
    return TaskQuickParse::parse($input, new DateTimeImmutable($now), $ignore);
}

test('9 · date, time and importance in one line', function () {
    $task = quick('Đóng tiền điện mai 8h !');

    expect($task->title)->toBe('Đóng tiền điện')
        ->and($task->dueOn?->format('Y-m-d'))->toBe('2026-11-19')
        ->and($task->dueTime)->toBe('08:00')
        ->and($task->important)->toBeTrue()
        ->and($task->repeat)->toBe(TaskRepeat::None)
        ->and($task->matches)->toBe([
            ['kind' => 'date', 'text' => 'mai'],
            ['kind' => 'time', 'text' => '8h'],
            ['kind' => 'important', 'text' => '!'],
        ]);
});

test('10 · full moon every lunar month', function () {
    $task = quick('thắp hương rằm hằng tháng');

    expect($task->title)->toBe('thắp hương')
        ->and($task->dueOn?->format('Y-m-d'))->toBe('2026-11-23')
        ->and($task->repeat)->toBe(TaskRepeat::MonthlyLunar)
        ->and($task->repeatDay)->toBe(15);
});

test('11 · a weekday means the next one, not today', function () {
    expect(quick('họp thứ 4')->dueOn?->format('Y-m-d'))->toBe('2026-11-25');
});

test('12 · a time alone means today', function () {
    $task = quick('gọi mẹ 8 giờ tối');

    expect($task->title)->toBe('gọi mẹ')
        ->and($task->dueOn?->format('Y-m-d'))->toBe('2026-11-18')
        ->and($task->dueTime)->toBe('20:00');
});

test('13 · a date inside the sentence can be ignored', function () {
    expect(quick('mua 2/9 lá cờ')->dueOn?->format('Y-m-d'))->toBe('2027-09-02')
        ->and(quick('mua 2/9 lá cờ')->title)->toBe('mua lá cờ');

    $ignored = quick('mua 2/9 lá cờ', ignore: [TaskQuickParse::KIND_DATE]);

    expect($ignored->title)->toBe('mua 2/9 lá cờ')
        ->and($ignored->dueOn)->toBeNull();
});

test('relative dates', function (string $input, string $expected) {
    expect(quick($input)->dueOn?->format('Y-m-d'))->toBe($expected);
})->with([
    'today' => ['việc hôm nay', '2026-11-18'],
    'unaccented today' => ['viec hom nay', '2026-11-18'],
    'tomorrow' => ['việc ngày mai', '2026-11-19'],
    'day after tomorrow' => ['việc mốt', '2026-11-20'],
    'weekend' => ['dọn nhà cuối tuần', '2026-11-21'],
    'next week' => ['việc tuần sau', '2026-11-23'],
    'friday next week' => ['việc thứ 6 tuần sau', '2026-11-27'],
    'sunday' => ['việc chủ nhật', '2026-11-22'],
    'weekday in words' => ['việc thứ hai', '2026-11-23'],
    'date with year' => ['việc 05/01/2027', '2027-01-05'],
    'date later this year' => ['việc ngày 24/12', '2026-12-24'],
    'lunar day' => ['cúng mùng 1', '2026-12-09'],
    'lunar day with month' => ['cúng rằm tháng giêng', '2027-02-20'],
]);

test('unaccented "mot" is not the day after tomorrow', function () {
    expect(quick('mua mot cai ao')->dueOn)->toBeNull();
});

test('words that only look like dates are left alone', function () {
    $task = quick('mãi mãi 25h 31/02');

    expect($task->dueOn)->toBeNull()
        ->and($task->dueTime)->toBeNull()
        ->and($task->title)->toBe('mãi mãi 25h 31/02');
});

test('times with minutes and parts of the day', function (string $input, string $expected) {
    expect(quick($input)->dueTime)->toBe($expected);
})->with([
    ['họp lúc 8h30', '08:30'],
    ['họp 14:15', '14:15'],
    ['ăn trưa 1h trưa', '13:00'],
    ['đón con 5 giờ chiều', '17:00'],
    ['ngủ 11h đêm', '23:00'],
    ['chạy bộ 6h sáng', '06:00'],
]);

test('"lúc" before the time is removed from the title', function () {
    expect(quick('họp lúc 8h30')->title)->toBe('họp');
});

test('repeats without a date start today', function (string $input, TaskRepeat $repeat, int $weekdays, ?int $day, string $due) {
    $task = quick($input);

    expect($task->repeat)->toBe($repeat)
        ->and($task->weekdays)->toBe($weekdays)
        ->and($task->repeatDay)->toBe($day)
        ->and($task->dueOn?->format('Y-m-d'))->toBe($due);
})->with([
    'daily' => ['tưới cây mỗi ngày', TaskRepeat::Daily, 0, null, '2026-11-18'],
    'weekly' => ['họp hằng tuần', TaskRepeat::Weekly, 0b0000100, null, '2026-11-18'],
    'monthly' => ['đóng tiền nhà hằng tháng', TaskRepeat::Monthly, 0, 18, '2026-11-18'],
    'yearly' => ['gia hạn bảo hiểm mỗi năm', TaskRepeat::Yearly, 0, 18, '2026-11-18'],
    'chosen weekdays' => ['đổ rác mỗi thứ 2 và thứ 5', TaskRepeat::Weekly, 0b0001001, null, '2026-11-19'],
    'weekday list' => ['đổ rác mỗi thứ 2, 5', TaskRepeat::Weekly, 0b0001001, null, '2026-11-19'],
    'first of every lunar month' => ['thắp hương mùng 1 hằng tháng', TaskRepeat::MonthlyLunar, 0, 1, '2026-12-09'],
]);

test('a repeat with a date uses that date as the anchor', function () {
    $task = quick('đóng tiền mạng hằng tháng 05/12');

    expect($task->dueOn?->format('Y-m-d'))->toBe('2026-12-05')
        ->and($task->repeatDay)->toBe(5)
        ->and($task->title)->toBe('đóng tiền mạng');
});

test('ignored kinds stay in the title', function () {
    $task = quick('việc quan trọng mai', ignore: [TaskQuickParse::KIND_IMPORTANT]);

    expect($task->important)->toBeFalse()
        ->and($task->title)->toBe('việc quan trọng');
});

test('only the first match of a kind is used', function () {
    $task = quick('chuyển từ mai sang 20/11');

    expect($task->dueOn?->format('Y-m-d'))->toBe('2026-11-19')
        ->and($task->title)->toBe('chuyển từ sang 20/11');
});
