<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\HolidayBreakKind;
use DateTimeImmutable;

/**
 * Một đợt nghỉ lễ liền nhau theo lịch của cán bộ, công chức (nghỉ thứ Bảy, Chủ nhật). PHP thuần.
 *
 * $days: ngày Y-m-d → lý do nghỉ (một trong các hằng DAY_*).
 */
final readonly class HolidayBreak
{
    public const DAY_HOLIDAY = 'le';

    public const DAY_COMPENSATORY = 'bu';

    public const DAY_WEEKEND = 'tuan';

    public const DAY_SWAPPED = 'them';

    /**
     * @param  array<string, string>  $days
     */
    public function __construct(
        public HolidayBreakKind $kind,
        public int $year,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public array $days,
        public bool $isOfficial,
        public ?DateTimeImmutable $makeUpOn = null,
        public ?string $source = null,
    ) {}

    public function length(): int
    {
        return count($this->days);
    }

    public function covers(DateTimeImmutable $date): bool
    {
        return isset($this->days[$date->format('Y-m-d')]);
    }

    public static function dayLabel(string $reason): string
    {
        return match ($reason) {
            self::DAY_HOLIDAY => 'Nghỉ lễ',
            self::DAY_COMPENSATORY => 'Nghỉ bù',
            self::DAY_WEEKEND => 'Nghỉ cuối tuần',
            default => 'Nghỉ thêm (hoán đổi)',
        };
    }
}
