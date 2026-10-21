<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Lịch các kỳ của một dây hụi chu kỳ tháng. PHP thuần.
 *
 * Ngày mở kỳ k luôn tính từ ngày bắt đầu (cộng k − 1 tháng, không tràn sang
 * tháng sau), không cộng dồn từ kỳ trước: bắt đầu 31/01 thì kỳ 2 là 28/02 và
 * kỳ 3 vẫn là 31/03.
 */
final class HuiSchedule
{
    public static function openDate(DateTimeImmutable $startDate, int $roundNo): DateTimeImmutable
    {
        if ($roundNo < 1) {
            throw new InvalidArgumentException('Số thứ tự kỳ phải từ 1 trở lên.');
        }

        $monthIndex = (int) $startDate->format('Y') * 12 + (int) $startDate->format('n') - 1 + ($roundNo - 1);
        $year = intdiv($monthIndex, 12);
        $month = $monthIndex % 12 + 1;
        $daysInMonth = (int) $startDate->setDate($year, $month, 1)->format('t');
        $day = min((int) $startDate->format('j'), $daysInMonth);

        return $startDate->setDate($year, $month, $day);
    }

    /**
     * Ngày mở của cả N kỳ.
     *
     * @return array<int, DateTimeImmutable> round_no => ngày mở
     */
    public static function openDates(DateTimeImmutable $startDate, int $totalSlots): array
    {
        $dates = [];

        for ($roundNo = 1; $roundNo <= $totalSlots; $roundNo++) {
            $dates[$roundNo] = self::openDate($startDate, $roundNo);
        }

        return $dates;
    }

    /**
     * Hạn đóng = ngày mở kỳ + số ngày hạn của dây.
     */
    public static function dueDate(DateTimeImmutable $openDate, int $dueOffsetDays): DateTimeImmutable
    {
        if ($dueOffsetDays < 0) {
            throw new InvalidArgumentException('Số ngày hạn đóng không được âm.');
        }

        return $openDate->modify("+{$dueOffsetDays} days");
    }
}
