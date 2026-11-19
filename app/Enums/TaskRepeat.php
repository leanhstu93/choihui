<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Cách lặp lại của một việc (document/10-viec-can-lam.md V6).
 */
enum TaskRepeat: string
{
    case None = 'none';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case MonthlyLunar = 'monthly_lunar';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Không lặp',
            self::Daily => 'Hằng ngày',
            self::Weekly => 'Hằng tuần',
            self::Monthly => 'Hằng tháng',
            self::MonthlyLunar => 'Hằng tháng âm',
            self::Yearly => 'Hằng năm',
        };
    }
}
