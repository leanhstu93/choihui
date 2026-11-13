<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\CanChi;
use App\Domain\LunarDayInfo;
use App\Domain\VietnamHolidays;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Lịch âm dương một năm cho khối lịch (trang giới thiệu và các trang /lich-am): thông tin từng ngày
 * dạng gọn của LunarDayInfo::toCompact() và các ngày lễ.
 */
final class YearCalendar
{
    public const MIN_YEAR = 1950;

    public const MAX_YEAR = 2100;

    public function __construct(private HolidaySchedule $holidaySchedule) {}

    public static function supports(int $year): bool
    {
        return $year >= self::MIN_YEAR && $year <= self::MAX_YEAR;
    }

    /**
     * months[m - 1][d - 1] là LunarDayInfo::toCompact() của ngày d/m; breaks là các đợt nghỉ lễ chạm vào
     * năm này (chính thức hoặc dự kiến, không lưu đệm để lịch quản trị nhập hiện ngay).
     *
     * @return array{year: int, months: list<list<array{int, int, int, int, int, int, int, int, int, int, int, int}>>, yearNames: array<int, string>, holidays: list<array{date: string, name: string, icon: string, dayOff: bool, lunar: ?string, about: string}>, breaks: list<array<string, mixed>>}
     */
    public function forYear(int $year): array
    {
        return $this->cachedYear($year) + [
            'breaks' => array_map(HolidaySchedule::toArray(...), $this->holidaySchedule->overlapping($year)),
        ];
    }

    /**
     * Phần không đổi của năm (ngày âm, ngày lễ), lưu đệm.
     *
     * @return array{year: int, months: list<list<array{int, int, int, int, int, int, int, int, int, int, int, int}>>, yearNames: array<int, string>, holidays: list<array{date: string, name: string, icon: string, dayOff: bool, lunar: ?string, about: string}>}
     */
    private function cachedYear(int $year): array
    {
        return Cache::remember("calendar:year:v3:{$year}", now()->addDays(30), function () use ($year): array {
            $months = [];
            $yearNames = [];

            foreach (range(1, 12) as $month) {
                $days = [];
                $daysInMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');

                foreach (range(1, $daysInMonth) as $day) {
                    $info = LunarDayInfo::of(new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day)));
                    $days[] = $info->toCompact();
                    $yearNames[$info->lunar->year] ??= CanChi::yearName($info->lunar->year);
                }

                $months[] = $days;
            }

            $holidays = array_map(fn (array $holiday): array => [
                'date' => $holiday['date']->format('Y-m-d'),
                'name' => $holiday['name'],
                'icon' => $holiday['icon'],
                'dayOff' => $holiday['dayOff'],
                'lunar' => $holiday['lunar']?->label(),
                'about' => $holiday['about'],
            ], VietnamHolidays::forYear($year));

            return ['year' => $year, 'months' => $months, 'yearNames' => $yearNames, 'holidays' => $holidays];
        });
    }
}
