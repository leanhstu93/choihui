<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\TaskRepeat;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Lần tới của việc lặp lại (document/10-viec-can-lam.md V6, mục 4). PHP thuần.
 *
 * Thứ trong tuần là bitmask 7 bit: bit 0 = thứ 2 … bit 6 = chủ nhật (ISO 1–7).
 * Ngày neo ($anchorDay) là ngày đã chọn cho hằng tháng (1–31 dương, 1–30 âm) và hằng năm;
 * trống thì lấy từ hạn hiện tại.
 */
final class TaskRecurrence
{
    public const int ALL_WEEKDAYS = 0b1111111;

    /**
     * Lần lặp đầu tiên sau max($due, $today): làm sớm không mất lần tới, làm trễ không đẻ ra
     * các lần đã qua.
     */
    public static function next(
        TaskRepeat $repeat,
        DateTimeImmutable $due,
        DateTimeImmutable $today,
        int $weekdays = 0,
        ?int $anchorDay = null,
    ): DateTimeImmutable {
        $due = $due->setTime(0, 0);
        $today = $today->setTime(0, 0);

        return self::after($repeat, max($due, $today), $due, $weekdays, $anchorDay);
    }

    /**
     * Lần lặp đầu tiên từ $from trở đi (chính $from cũng tính), dùng khi đặt hạn cho việc mới
     * như "rằm hằng tháng" hay "mỗi thứ 2". Ngày neo trống thì lấy từ $from.
     */
    public static function firstOnOrAfter(
        TaskRepeat $repeat,
        DateTimeImmutable $from,
        int $weekdays = 0,
        ?int $anchorDay = null,
    ): DateTimeImmutable {
        $from = $from->setTime(0, 0);

        return self::after($repeat, $from->modify('-1 day'), $from, $weekdays, $anchorDay);
    }

    /**
     * $count lần tới liên tiếp sau $due, để xem trước trong form (VL-02).
     *
     * @return list<DateTimeImmutable>
     */
    public static function preview(
        TaskRepeat $repeat,
        DateTimeImmutable $due,
        int $count,
        int $weekdays = 0,
        ?int $anchorDay = null,
    ): array {
        $dates = [];
        $current = $due->setTime(0, 0);

        for ($i = 0; $i < $count; $i++) {
            $current = self::after($repeat, $current, $due->setTime(0, 0), $weekdays, $anchorDay);
            $dates[] = $current;
        }

        return $dates;
    }

    /**
     * Bit của một ngày trong bitmask thứ.
     */
    public static function weekdayBit(DateTimeImmutable $date): int
    {
        return 1 << ((int) $date->format('N') - 1);
    }

    /**
     * Lần lặp đầu tiên sau hẳn $base. $reference cho ngày neo mặc định.
     */
    private static function after(
        TaskRepeat $repeat,
        DateTimeImmutable $base,
        DateTimeImmutable $reference,
        int $weekdays,
        ?int $anchorDay,
    ): DateTimeImmutable {
        return match ($repeat) {
            TaskRepeat::None => throw new InvalidArgumentException('Việc không lặp lại.'),
            TaskRepeat::Daily => $base->modify('+1 day'),
            TaskRepeat::Weekly => self::nextWeekly($base, $weekdays & self::ALL_WEEKDAYS ?: self::weekdayBit($reference)),
            TaskRepeat::Monthly => self::nextMonthly($base, $anchorDay ?? (int) $reference->format('j')),
            TaskRepeat::MonthlyLunar => self::nextMonthlyLunar($base, $anchorDay ?? LunarCalendar::fromSolar($reference)->day),
            TaskRepeat::Yearly => FamilyCalendar::nextOccurrence(
                false,
                $anchorDay ?? (int) $reference->format('j'),
                (int) $reference->format('n'),
                $base->modify('+1 day'),
            ),
        };
    }

    private static function nextWeekly(DateTimeImmutable $base, int $weekdays): DateTimeImmutable
    {
        for ($i = 1; $i <= 7; $i++) {
            $date = $base->modify("+{$i} day");

            if (($weekdays & self::weekdayBit($date)) !== 0) {
                return $date;
            }
        }

        throw new InvalidArgumentException('Chưa chọn thứ nào.');
    }

    /**
     * Ngày N mỗi tháng dương; tháng không có ngày N thì lấy ngày cuối tháng.
     */
    private static function nextMonthly(DateTimeImmutable $base, int $anchorDay): DateTimeImmutable
    {
        if ($anchorDay < 1 || $anchorDay > 31) {
            throw new InvalidArgumentException('Ngày lặp hằng tháng không hợp lệ.');
        }

        $monthStart = $base->modify('first day of this month');

        for ($i = 0; $i < 2; $i++) {
            $month = $monthStart->modify("+{$i} month");
            $day = min($anchorDay, (int) $month->format('t'));
            $date = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $day);

            if ($date > $base) {
                return $date;
            }
        }

        throw new InvalidArgumentException('Không tìm được lần tới.');
    }

    /**
     * Ngày N mỗi tháng âm, kể cả tháng nhuận; ngày 30 gặp tháng thiếu thì lấy 29.
     */
    private static function nextMonthlyLunar(DateTimeImmutable $base, int $anchorDay): DateTimeImmutable
    {
        if ($anchorDay < 1 || $anchorDay > 30) {
            throw new InvalidArgumentException('Ngày âm lặp hằng tháng không hợp lệ.');
        }

        $monthStart = $base->modify('-'.(LunarCalendar::fromSolar($base)->day - 1).' day');

        for ($i = 0; $i < 2; $i++) {
            $length = LunarCalendar::fromSolar($monthStart->modify('+29 day'))->day === 1 ? 29 : 30;
            $date = $monthStart->modify('+'.(min($anchorDay, $length) - 1).' day');

            if ($date > $base) {
                return $date;
            }

            $monthStart = $monthStart->modify("+{$length} day");
        }

        throw new InvalidArgumentException('Không tìm được lần tới.');
    }
}
