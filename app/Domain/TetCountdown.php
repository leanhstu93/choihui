<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * Đếm ngược tới Tết Nguyên đán cho trang chủ. PHP thuần.
 *
 * Từ mùng 1 tới mùng 3 Tết thì báo đang Tết ($tetDay), còn lại đếm tới mùng 1 tháng Giêng lần tới.
 */
final readonly class TetCountdown
{
    private const CAN = ['Giáp', 'Ất', 'Bính', 'Đinh', 'Mậu', 'Kỷ', 'Canh', 'Tân', 'Nhâm', 'Quý'];

    private const CHI = ['Tý', 'Sửu', 'Dần', 'Mão', 'Thìn', 'Tỵ', 'Ngọ', 'Mùi', 'Thân', 'Dậu', 'Tuất', 'Hợi'];

    private const CHI_ICON = ['🐭', '🐃', '🐯', '🐱', '🐲', '🐍', '🐴', '🐐', '🐵', '🐔', '🐶', '🐷'];

    public function __construct(
        public DateTimeImmutable $tet,
        public int $daysLeft,
        public int $lunarYear,
        public ?int $tetDay = null,
    ) {}

    public static function from(DateTimeImmutable $today): self
    {
        $today = $today->setTime(0, 0);
        $lunar = LunarCalendar::fromSolar($today);

        if ($lunar->month === 1 && ! $lunar->isLeap && $lunar->day <= 3) {
            $tet = LunarCalendar::toSolar(new LunarDate(1, 1, $lunar->year));

            return new self($tet, 0, $lunar->year, $lunar->day);
        }

        $tet = FamilyCalendar::nextOccurrence(true, 1, 1, $today);

        return new self($tet, FamilyCalendar::daysBetween($today, $tet), LunarCalendar::fromSolar($tet)->year);
    }

    /**
     * Tên năm can chi, ví dụ 2027 → "Đinh Mùi".
     */
    public static function yearName(int $lunarYear): string
    {
        return self::CAN[($lunarYear + 6) % 10].' '.self::CHI[($lunarYear + 8) % 12];
    }

    public function name(): string
    {
        return self::yearName($this->lunarYear);
    }

    public function icon(): string
    {
        return self::CHI_ICON[($this->lunarYear + 8) % 12];
    }

    public function isTet(): bool
    {
        return $this->tetDay !== null;
    }
}
