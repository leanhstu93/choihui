<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ngày nhớ tính theo dương lịch hay âm lịch (document/09-gia-dinh.md G3, G4).
 */
enum CalendarType: string
{
    case Duong = 'duong';
    case Am = 'am';

    public function label(): string
    {
        return match ($this) {
            self::Duong => 'Dương lịch',
            self::Am => 'Âm lịch',
        };
    }

    public function isLunar(): bool
    {
        return $this === self::Am;
    }
}
