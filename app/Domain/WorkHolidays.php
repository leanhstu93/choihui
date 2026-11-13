<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\HolidayBreakKind;
use DateTimeImmutable;

/**
 * Lịch nghỉ lễ hưởng lương theo luật, tính tự động cho mọi năm (bản dự kiến), và ghép lịch chính thức
 * khi đã có thông báo. PHP thuần.
 *
 * Quy tắc: ngày lễ trùng thứ Bảy, Chủ nhật thì nghỉ bù vào ngày làm việc kế tiếp (BLLĐ 2019 Điều 111);
 * đợt nghỉ gồm cả cuối tuần liền kề. Tết Nguyên đán ước theo cách hay chọn: 2 ngày cuối năm, 3 ngày
 * đầu năm. Quốc khánh nghỉ 2/9 và thêm 1/9 hoặc 3/9, chọn ngày cho đợt nghỉ dài hơn.
 */
final class WorkHolidays
{
    /**
     * Các đợt nghỉ của năm $year, xếp theo ngày bắt đầu. $official là lịch chính thức đã nhập, theo loại.
     *
     * @param  array<string, array{start: DateTimeImmutable, end: DateTimeImmutable, makeUpOn: ?DateTimeImmutable, source: ?string}>  $official
     * @return list<HolidayBreak>
     */
    public static function forYear(int $year, array $official = []): array
    {
        $breaks = [];

        foreach (HolidayBreakKind::cases() as $kind) {
            if ($year < $kind->sinceYear()) {
                continue;
            }

            $breaks[] = isset($official[$kind->value])
                ? self::official($kind, $year, $official[$kind->value])
                : self::estimate($kind, $year);
        }

        usort($breaks, fn (HolidayBreak $a, HolidayBreak $b): int => $a->start <=> $b->start);

        return $breaks;
    }

    /**
     * Ngày lễ theo luật của đợt nghỉ (chưa tính nghỉ bù).
     *
     * @return list<DateTimeImmutable>
     */
    public static function statutoryDays(HolidayBreakKind $kind, int $year): array
    {
        $date = fn (int $month, int $day): DateTimeImmutable => new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));

        return match ($kind) {
            HolidayBreakKind::TetDuongLich => [$date(1, 1)],
            HolidayBreakKind::TetNguyenDan => self::tetDays($year),
            HolidayBreakKind::GioTo => [LunarCalendar::toSolar(new LunarDate(10, 3, $year))],
            HolidayBreakKind::ThongNhat => [$date(4, 30), $date(5, 1)],
            HolidayBreakKind::QuocKhanh => self::nationalDayDays($year),
            HolidayBreakKind::VanHoa => [$date(11, 24)],
        };
    }

    public static function estimate(HolidayBreakKind $kind, int $year): HolidayBreak
    {
        $days = self::withCompensation(self::statutoryDays($kind, $year));
        $days = self::withAdjacentWeekends($days);
        $dates = array_keys($days);

        return new HolidayBreak($kind, $year, new DateTimeImmutable($dates[0]), new DateTimeImmutable($dates[count($dates) - 1]), $days, isOfficial: false);
    }

    /**
     * @param  array{start: DateTimeImmutable, end: DateTimeImmutable, makeUpOn: ?DateTimeImmutable, source: ?string}  $entry
     */
    public static function official(HolidayBreakKind $kind, int $year, array $entry): HolidayBreak
    {
        $estimated = self::withCompensation(self::statutoryDays($kind, $year));
        $days = [];

        for ($day = $entry['start']->setTime(0, 0); $day <= $entry['end']; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $days[$key] = match (true) {
                ($estimated[$key] ?? null) === HolidayBreak::DAY_HOLIDAY => HolidayBreak::DAY_HOLIDAY,
                self::isWeekend($day) => HolidayBreak::DAY_WEEKEND,
                isset($estimated[$key]) => HolidayBreak::DAY_COMPENSATORY,
                default => HolidayBreak::DAY_SWAPPED,
            };
        }

        return new HolidayBreak($kind, $year, $entry['start']->setTime(0, 0), $entry['end']->setTime(0, 0), $days, true, $entry['makeUpOn'], $entry['source']);
    }

    public static function isWeekend(DateTimeImmutable $date): bool
    {
        return (int) $date->format('N') >= 6;
    }

    /**
     * Ngày lễ kèm ngày nghỉ bù cho những ngày lễ trùng cuối tuần.
     *
     * @param  list<DateTimeImmutable>  $holidays
     * @return array<string, string>
     */
    private static function withCompensation(array $holidays): array
    {
        $days = [];
        foreach ($holidays as $holiday) {
            $days[$holiday->format('Y-m-d')] = HolidayBreak::DAY_HOLIDAY;
        }

        foreach ($holidays as $holiday) {
            if (! self::isWeekend($holiday)) {
                continue;
            }

            $next = $holiday->modify('+1 day');
            while (self::isWeekend($next) || isset($days[$next->format('Y-m-d')])) {
                $next = $next->modify('+1 day');
            }
            $days[$next->format('Y-m-d')] = HolidayBreak::DAY_COMPENSATORY;
        }

        ksort($days);

        return $days;
    }

    /**
     * Nối các ngày nghỉ với thứ Bảy, Chủ nhật liền trước và liền sau (và lấp cuối tuần ở giữa).
     *
     * @param  array<string, string>  $days
     * @return array<string, string>
     */
    private static function withAdjacentWeekends(array $days): array
    {
        $keys = array_keys($days);
        $start = new DateTimeImmutable($keys[0]);
        $end = new DateTimeImmutable($keys[count($keys) - 1]);

        while (self::isWeekend($start->modify('-1 day'))) {
            $start = $start->modify('-1 day');
        }
        while (self::isWeekend($end->modify('+1 day'))) {
            $end = $end->modify('+1 day');
        }

        $result = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $key = $day->format('Y-m-d');
            $result[$key] = $days[$key] ?? HolidayBreak::DAY_WEEKEND;
        }

        return $result;
    }

    /**
     * 5 ngày Tết: 2 ngày cuối năm âm trước và 3 ngày đầu năm âm $year.
     *
     * @return list<DateTimeImmutable>
     */
    private static function tetDays(int $year): array
    {
        $tet = LunarCalendar::toSolar(new LunarDate(1, 1, $year));

        return array_map(fn (int $offset): DateTimeImmutable => $tet->modify(sprintf('%+d day', $offset)), [-2, -1, 0, 1, 2]);
    }

    /**
     * 2/9 và 1/9 hoặc 3/9: chọn ngày cho đợt nghỉ dài hơn, bằng nhau thì lấy 1/9.
     *
     * @return list<DateTimeImmutable>
     */
    private static function nationalDayDays(int $year): array
    {
        $nationalDay = new DateTimeImmutable("{$year}-09-02");
        $best = null;
        $bestLength = 0;

        foreach ([$nationalDay->modify('-1 day'), $nationalDay->modify('+1 day')] as $extra) {
            $days = [$extra < $nationalDay ? $extra : $nationalDay, $extra < $nationalDay ? $nationalDay : $extra];
            $length = count(self::withAdjacentWeekends(self::withCompensation($days)));

            if ($length > $bestLength) {
                $best = $days;
                $bestLength = $length;
            }
        }

        return $best ?? [$nationalDay];
    }
}
