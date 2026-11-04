<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Một ngày âm lịch. Tháng nhuận hiển thị có chữ N, ví dụ 15/5N ÂL.
 */
final readonly class LunarDate
{
    public function __construct(
        public int $day,
        public int $month,
        public int $year,
        public bool $isLeap = false,
    ) {}

    public function label(): string
    {
        return sprintf('%d/%d%s ÂL', $this->day, $this->month, $this->isLeap ? 'N' : '');
    }
}
