<?php

declare(strict_types=1);

use App\Domain\HolidayBreak;
use App\Domain\WorkHolidays;
use App\Enums\HolidayBreakKind;

/*
 * Lịch nghỉ lễ tự tính theo Bộ luật Lao động (dự kiến) và lịch chính thức đã nhập.
 */

function estimated(HolidayBreakKind $kind, int $year): HolidayBreak
{
    return WorkHolidays::estimate($kind, $year);
}

test('the Tết estimate matches the announced schedules', function (int $year, string $start, string $end, array $compensatory) {
    $break = estimated(HolidayBreakKind::TetNguyenDan, $year);

    expect($break->start->format('Y-m-d'))->toBe($start)
        ->and($break->end->format('Y-m-d'))->toBe($end)
        ->and(array_keys(array_filter($break->days, fn (string $reason): bool => $reason === HolidayBreak::DAY_COMPENSATORY)))->toBe($compensatory)
        ->and($break->isOfficial)->toBeFalse();
})->with([
    // Tết Bính Ngọ: thứ Bảy 14/2 tới Chủ nhật 22/2/2026.
    '2026' => [2026, '2026-02-14', '2026-02-22', ['2026-02-20']],
    // Thông báo 11483/TB-BNV: thứ Năm 4/2 tới thứ Tư 10/2/2027, nghỉ bù 2 ngày.
    '2027' => [2027, '2027-02-04', '2027-02-10', ['2027-02-09', '2027-02-10']],
]);

test('a holiday on a weekend gives a compensatory day on the next working day', function () {
    // 1/5/2027 là thứ Bảy: nghỉ bù thứ Hai 3/5.
    expect(estimated(HolidayBreakKind::ThongNhat, 2027)->days)->toBe([
        '2027-04-30' => HolidayBreak::DAY_HOLIDAY,
        '2027-05-01' => HolidayBreak::DAY_HOLIDAY,
        '2027-05-02' => HolidayBreak::DAY_WEEKEND,
        '2027-05-03' => HolidayBreak::DAY_COMPENSATORY,
    ])
        // 1/1/2028 là thứ Bảy: nghỉ bù thứ Hai 3/1.
        ->and(estimated(HolidayBreakKind::TetDuongLich, 2028)->days['2028-01-03'])->toBe(HolidayBreak::DAY_COMPENSATORY);
});

test('the national day takes the neighbouring day that makes the longer break', function () {
    // 2/9/2027 thứ Năm: nghỉ thêm 3/9 rồi liền cuối tuần, như thông báo chính thức.
    $break = estimated(HolidayBreakKind::QuocKhanh, 2027);

    expect($break->start->format('Y-m-d'))->toBe('2027-09-02')
        ->and($break->end->format('Y-m-d'))->toBe('2027-09-05');
});

test('Vietnamese Culture Day is a day off from 2026 only', function () {
    $kinds = fn (int $year): array => array_map(fn (HolidayBreak $break): HolidayBreakKind => $break->kind, WorkHolidays::forYear($year));

    expect($kinds(2026))->toContain(HolidayBreakKind::VanHoa)
        ->and($kinds(2025))->not->toContain(HolidayBreakKind::VanHoa)
        ->and(estimated(HolidayBreakKind::VanHoa, 2026)->days)->toBe(['2026-11-24' => HolidayBreak::DAY_HOLIDAY]);
});

test('an official schedule replaces the estimate and labels swapped days', function () {
    $breaks = WorkHolidays::forYear(2027, ['gio_to' => [
        'start' => new DateTimeImmutable('2027-04-15'),
        'end' => new DateTimeImmutable('2027-04-18'),
        'makeUpOn' => new DateTimeImmutable('2027-04-24'),
        'source' => 'Thông báo thử',
    ]]);
    $gioTo = collect($breaks)->firstWhere('kind', HolidayBreakKind::GioTo);

    expect($gioTo->isOfficial)->toBeTrue()
        ->and($gioTo->days)->toBe([
            '2027-04-15' => HolidayBreak::DAY_SWAPPED,
            '2027-04-16' => HolidayBreak::DAY_HOLIDAY,
            '2027-04-17' => HolidayBreak::DAY_WEEKEND,
            '2027-04-18' => HolidayBreak::DAY_WEEKEND,
        ])
        ->and($gioTo->makeUpOn?->format('Y-m-d'))->toBe('2027-04-24')
        ->and(collect($breaks)->firstWhere('kind', HolidayBreakKind::TetNguyenDan)->isOfficial)->toBeFalse()
        ->and(array_map(fn (HolidayBreak $break): string => $break->start->format('m-d'), $breaks))->toBe(collect($breaks)->map(fn ($b) => $b->start->format('m-d'))->sort()->values()->all());
});
