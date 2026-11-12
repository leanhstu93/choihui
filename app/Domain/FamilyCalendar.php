<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Ngày nhớ lặp lại hằng năm theo dương hoặc âm lịch (document/09-gia-dinh.md mục 4). PHP thuần.
 */
final class FamilyCalendar
{
    /**
     * Lần tới của ngày nhớ, tính từ $from (chính ngày $from cũng tính).
     *
     * Ngày âm: tháng thường, không lấy tháng nhuận (G3); ngày 30 gặp tháng thiếu lùi về 29.
     * Ngày dương: 29/02 gặp năm không nhuận lùi về 28/02 (G4).
     */
    public static function nextOccurrence(bool $lunar, int $day, int $month, DateTimeImmutable $from): DateTimeImmutable
    {
        self::assertValid($lunar, $day, $month);
        $from = $from->setTime(0, 0);
        $year = $lunar ? LunarCalendar::fromSolar($from)->year : (int) $from->format('Y');

        $date = self::inYear($lunar, $day, $month, $year);

        return $date < $from ? self::inYear($lunar, $day, $month, $year + 1) : $date;
    }

    /**
     * Số năm tính từ năm gốc tới lần $occurrence (ngày âm dùng năm âm). Null khi không có năm
     * gốc hoặc năm gốc chưa tới.
     */
    public static function yearsSince(bool $lunar, ?int $originYear, DateTimeImmutable $occurrence): ?int
    {
        if ($originYear === null) {
            return null;
        }

        $year = $lunar ? LunarCalendar::fromSolar($occurrence)->year : (int) $occurrence->format('Y');
        $years = $year - $originYear;

        return $years > 0 ? $years : null;
    }

    /**
     * Số ngày từ $from tới $to (0 là cùng ngày).
     */
    public static function daysBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $gap = $from->setTime(0, 0)->diff($to->setTime(0, 0));

        return $gap->invert === 1 ? -(int) $gap->days : (int) $gap->days;
    }

    /**
     * Ngày / tháng có thật hay không (ngày dương theo năm nhuận, nên 29/02 hợp lệ).
     */
    public static function isValid(bool $lunar, int $day, int $month): bool
    {
        if ($month < 1 || $month > 12 || $day < 1) {
            return false;
        }

        return $lunar ? $day <= 30 : checkdate($month, $day, 2024);
    }

    private static function inYear(bool $lunar, int $day, int $month, int $year): DateTimeImmutable
    {
        if ($lunar) {
            try {
                return LunarCalendar::toSolar(new LunarDate($day, $month, $year));
            } catch (InvalidArgumentException) {
                return LunarCalendar::toSolar(new LunarDate(29, $month, $year));
            }
        }

        $day = checkdate($month, $day, $year) ? $day : 28;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    private static function assertValid(bool $lunar, int $day, int $month): void
    {
        if (! self::isValid($lunar, $day, $month)) {
            throw new InvalidArgumentException('Ngày nhớ không hợp lệ.');
        }
    }
}
