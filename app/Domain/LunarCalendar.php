<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Âm lịch Việt Nam, tính thiên văn theo múi giờ UTC+7 (thuật toán Hồ Ngọc Đức).
 * Múi giờ phải cố định: tính theo UTC+8 sẽ ra lịch Trung Quốc, có năm lệch Tết
 * một ngày hoặc cả tháng. PHP thuần.
 */
final class LunarCalendar
{
    private const int TIME_ZONE = 7;

    private const float SYNODIC_MONTH = 29.530588853;

    private const float EPOCH = 2415021.076998695;

    public static function fromSolar(DateTimeInterface $date): LunarDate
    {
        $year = (int) $date->format('Y');
        $dayNumber = self::julianDay((int) $date->format('j'), (int) $date->format('n'), $year);
        $monthStart = self::newMoonDay(self::monthIndex($dayNumber));

        $a11 = self::month11($year);
        $b11 = $a11;

        if ($a11 >= $monthStart) {
            $lunarYear = $year;
            $a11 = self::month11($year - 1);
        } else {
            $lunarYear = $year + 1;
            $b11 = self::month11($year + 1);
        }

        $diff = (int) floor(($monthStart - $a11) / 29);
        $isLeap = false;
        $month = $diff + 11;

        if ($b11 - $a11 > 365) {
            $leapDiff = self::leapMonthOffset($a11);

            if ($diff >= $leapDiff) {
                $month = $diff + 10;
                $isLeap = $diff === $leapDiff;
            }
        }

        if ($month > 12) {
            $month -= 12;
        }

        if ($month >= 11 && $diff < 4) {
            $lunarYear--;
        }

        return new LunarDate($dayNumber - $monthStart + 1, $month, $lunarYear, $isLeap);
    }

    /**
     * @throws InvalidArgumentException Khi ngày âm không có thật: tháng nhuận sai năm, hoặc ngày 30 của tháng thiếu.
     */
    public static function toSolar(LunarDate $date): DateTimeImmutable
    {
        if ($date->month < 1 || $date->month > 12 || $date->day < 1 || $date->day > 30) {
            throw new InvalidArgumentException('Ngày âm lịch không hợp lệ.');
        }

        if ($date->isLeap && self::leapMonth($date->year) !== $date->month) {
            throw new InvalidArgumentException("Năm {$date->year} không có tháng {$date->month} nhuận.");
        }

        $monthIndex = self::lunarMonthIndex($date);
        $monthStart = self::newMoonDay($monthIndex);
        $daysInMonth = self::newMoonDay($monthIndex + 1) - $monthStart;

        if ($date->day > $daysInMonth) {
            throw new InvalidArgumentException("Tháng này chỉ có {$daysInMonth} ngày.");
        }

        return self::dateFromJulianDay($monthStart + $date->day - 1);
    }

    /**
     * Tháng nhuận của một năm âm lịch, null nếu năm đó không nhuận.
     */
    public static function leapMonth(int $year): ?int
    {
        $leapMonth = self::leapMonthBetween(self::month11($year - 1), self::month11($year));

        if ($leapMonth !== null && $leapMonth <= 10) {
            return $leapMonth;
        }

        $leapMonth = self::leapMonthBetween(self::month11($year), self::month11($year + 1));

        return $leapMonth !== null && $leapMonth >= 11 ? $leapMonth : null;
    }

    /**
     * Cùng ngày âm của tháng âm thứ $months sau $start; mọi tháng đều được tính,
     * kể cả tháng nhuận. Tháng thiếu không có ngày 30 thì lùi về ngày 29.
     */
    public static function addMonths(DateTimeImmutable $start, int $months): DateTimeImmutable
    {
        $startDay = self::julianDay((int) $start->format('j'), (int) $start->format('n'), (int) $start->format('Y'));
        $startIndex = self::monthIndex($startDay);
        $day = $startDay - self::newMoonDay($startIndex) + 1;

        $monthStart = self::newMoonDay($startIndex + $months);
        $daysInMonth = self::newMoonDay($startIndex + $months + 1) - $monthStart;

        return self::dateFromJulianDay($monthStart + min($day, $daysInMonth) - 1);
    }

    /**
     * Số thứ tự của tháng âm chứa ngày Julian $dayNumber.
     */
    /**
     * Số ngày Julian của một ngày dương (dùng tính can chi ngày).
     */
    public static function julianDayOf(DateTimeInterface $date): int
    {
        return self::julianDay((int) $date->format('j'), (int) $date->format('n'), (int) $date->format('Y'));
    }

    /**
     * Tiết khí (0 = Xuân phân … 23 = Kinh trập) của ngày dương, tính theo vị trí mặt trời cuối ngày.
     */
    public static function solarTerm(DateTimeInterface $date): int
    {
        return (int) floor(self::sunLongitude(self::julianDayOf($date) + 1) / M_PI * 12);
    }

    private static function monthIndex(int $dayNumber): int
    {
        $index = (int) floor(($dayNumber - self::EPOCH) / self::SYNODIC_MONTH);

        return self::newMoonDay($index + 1) <= $dayNumber ? $index + 1 : $index;
    }

    private static function lunarMonthIndex(LunarDate $date): int
    {
        [$a11, $b11] = $date->month < 11
            ? [self::month11($date->year - 1), self::month11($date->year)]
            : [self::month11($date->year), self::month11($date->year + 1)];

        $index = (int) floor(0.5 + ($a11 - self::EPOCH) / self::SYNODIC_MONTH);
        $offset = $date->month - 11;

        if ($offset < 0) {
            $offset += 12;
        }

        if ($b11 - $a11 > 365) {
            $leapOffset = self::leapMonthOffset($a11);

            if ($date->isLeap || $offset >= $leapOffset) {
                $offset++;
            }
        }

        return $index + $offset;
    }

    private static function leapMonthBetween(int $a11, int $b11): ?int
    {
        if ($b11 - $a11 <= 365) {
            return null;
        }

        $leapMonth = self::leapMonthOffset($a11) - 2;

        return $leapMonth < 0 ? $leapMonth + 12 : $leapMonth;
    }

    private static function julianDay(int $day, int $month, int $year): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    }

    private static function dateFromJulianDay(int $dayNumber): DateTimeImmutable
    {
        $a = $dayNumber + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv($b * 146097, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        $day = $e - intdiv(153 * $m + 2, 5) + 1;
        $month = $m + 3 - 12 * intdiv($m, 10);
        $year = $b * 100 + $d - 4800 + intdiv($m, 10);

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /**
     * Ngày Julian có sóc (trăng non) thứ $k tính từ 01/01/1900.
     */
    private static function newMoonDay(int $k): int
    {
        $t = $k / 1236.85;
        $t2 = $t * $t;
        $t3 = $t2 * $t;
        $dr = M_PI / 180;

        $jd = 2415020.75933 + 29.53058868 * $k + 0.0001178 * $t2 - 0.000000155 * $t3;
        $jd += 0.00033 * sin((166.56 + 132.87 * $t - 0.009173 * $t2) * $dr);

        $m = 359.2242 + 29.10535608 * $k - 0.0000333 * $t2 - 0.00000347 * $t3;
        $mpr = 306.0253 + 385.81691806 * $k + 0.0107306 * $t2 + 0.00001236 * $t3;
        $f = 21.2964 + 390.67050646 * $k - 0.0016528 * $t2 - 0.00000239 * $t3;

        $c1 = (0.1734 - 0.000393 * $t) * sin($m * $dr)
            + 0.0021 * sin(2 * $dr * $m)
            - 0.4068 * sin($mpr * $dr)
            + 0.0161 * sin($dr * 2 * $mpr)
            - 0.0004 * sin($dr * 3 * $mpr)
            + 0.0104 * sin($dr * 2 * $f)
            - 0.0051 * sin($dr * ($m + $mpr))
            - 0.0074 * sin($dr * ($m - $mpr))
            + 0.0004 * sin($dr * (2 * $f + $m))
            - 0.0004 * sin($dr * (2 * $f - $m))
            - 0.0006 * sin($dr * (2 * $f + $mpr))
            + 0.0010 * sin($dr * (2 * $f - $mpr))
            + 0.0005 * sin($dr * (2 * $mpr + $m));

        $deltaT = $t < -11
            ? 0.001 + 0.000839 * $t + 0.0002261 * $t2 - 0.00000845 * $t3 - 0.000000081 * $t * $t3
            : -0.000278 + 0.000265 * $t + 0.000262 * $t2;

        return (int) floor($jd + $c1 - $deltaT + 0.5 + self::TIME_ZONE / 24);
    }

    /**
     * Cung hoàng đạo (0–11) của mặt trời vào ngày Julian $dayNumber.
     */
    private static function sunLongitudeSector(int $dayNumber): int
    {
        return (int) floor(self::sunLongitude($dayNumber) / M_PI * 6);
    }

    /**
     * Kinh độ mặt trời (radian, 0 tới 2π) lúc 0 giờ ngày Julian $dayNumber theo giờ Việt Nam.
     */
    private static function sunLongitude(int $dayNumber): float
    {
        $t = ($dayNumber - 2451545.5 - self::TIME_ZONE / 24) / 36525;
        $t2 = $t * $t;
        $dr = M_PI / 180;

        $m = 357.52910 + 35999.05030 * $t - 0.0001559 * $t2 - 0.00000048 * $t * $t2;
        $l0 = 280.46645 + 36000.76983 * $t + 0.0003032 * $t2;

        $dl = (1.914600 - 0.004817 * $t - 0.000014 * $t2) * sin($dr * $m);
        $dl += (0.019993 - 0.000101 * $t) * sin($dr * 2 * $m) + 0.000290 * sin($dr * 3 * $m);

        $omega = 125.04 - 1934.136 * $t;
        $longitude = ($l0 + $dl - 0.00569 - 0.00478 * sin($omega * $dr)) * $dr;

        return $longitude - M_PI * 2 * floor($longitude / (M_PI * 2));
    }

    /**
     * Ngày Julian mùng 1 tháng 11 âm của năm dương $year (tháng chứa đông chí).
     */
    private static function month11(int $year): int
    {
        $offset = self::julianDay(31, 12, $year) - 2415021;
        $k = (int) floor($offset / self::SYNODIC_MONTH);
        $newMoon = self::newMoonDay($k);

        return self::sunLongitudeSector($newMoon) >= 9 ? self::newMoonDay($k - 1) : $newMoon;
    }

    /**
     * Vị trí tháng nhuận tính từ tháng 11 âm $a11: tháng đầu tiên không có trung khí.
     */
    private static function leapMonthOffset(int $a11): int
    {
        $k = (int) floor(($a11 - self::EPOCH) / self::SYNODIC_MONTH + 0.5);
        $index = 1;
        $sector = self::sunLongitudeSector(self::newMoonDay($k + $index));

        do {
            $last = $sector;
            $index++;
            $sector = self::sunLongitudeSector(self::newMoonDay($k + $index));
        } while ($sector !== $last && $index < 14);

        return $index - 1;
    }
}
