<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\LunarCalendar;
use App\Domain\TetCountdown;
use App\Domain\VietnamHolidays;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Lịch âm dương một năm cho phần lịch trên trang giới thiệu: ngày âm của từng ngày và các ngày lễ.
 */
final class YearCalendar
{
    public const MIN_YEAR = 1950;

    public const MAX_YEAR = 2100;

    /**
     * months[m - 1][d - 1] = [ngày âm, tháng âm, nhuận 0/1, năm âm].
     *
     * @return array{year: int, months: list<list<array{int, int, int, int}>>, yearNames: array<int, string>, holidays: list<array{date: string, name: string, icon: string, dayOff: bool, lunar: ?string}>}
     */
    public function forYear(int $year): array
    {
        return Cache::remember("calendar:year:v1:{$year}", now()->addDays(30), function () use ($year): array {
            $months = [];
            $yearNames = [];

            foreach (range(1, 12) as $month) {
                $days = [];
                $daysInMonth = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');

                foreach (range(1, $daysInMonth) as $day) {
                    $lunar = LunarCalendar::fromSolar(new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day)));
                    $days[] = [$lunar->day, $lunar->month, $lunar->isLeap ? 1 : 0, $lunar->year];
                    $yearNames[$lunar->year] ??= TetCountdown::yearName($lunar->year);
                }

                $months[] = $days;
            }

            $holidays = array_map(fn (array $holiday): array => [
                'date' => $holiday['date']->format('Y-m-d'),
                'name' => $holiday['name'],
                'icon' => $holiday['icon'],
                'dayOff' => $holiday['dayOff'],
                'lunar' => $holiday['lunar']?->label(),
            ], VietnamHolidays::forYear($year));

            return ['year' => $year, 'months' => $months, 'yearNames' => $yearNames, 'holidays' => $holidays];
        });
    }
}
