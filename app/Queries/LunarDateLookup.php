<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\FamilyCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Bảng tra "ngày âm d/m lần tới rơi vào ngày dương nào" cho phần thử trên trang giới thiệu.
 * Cùng quy tắc với ngày nhớ (document/09-gia-dinh.md G3): tháng thường, ngày 30 gặp tháng thiếu lùi về 29.
 */
final class LunarDateLookup
{
    /**
     * Khóa "d/m" → ngày dương Y-m-d của lần tới, tính từ $today. Lưu đệm theo ngày.
     *
     * @return array<string, string>
     */
    public function from(CarbonImmutable $today): array
    {
        $today = $today->startOfDay();

        return Cache::remember('landing:lunar-lookup:'.$today->toDateString(), now()->addDay(), function () use ($today): array {
            $table = [];

            foreach (range(1, 12) as $month) {
                foreach (range(1, 30) as $day) {
                    $table["{$day}/{$month}"] = FamilyCalendar::nextOccurrence(true, $day, $month, $today->toDateTimeImmutable())->format('Y-m-d');
                }
            }

            return $table;
        });
    }
}
