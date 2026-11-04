<?php

declare(strict_types=1);

namespace App\Enums;

enum HuiFrequency: string
{
    case Thang = 'thang';
    case ThangAm = 'thang_am';

    public function isLunar(): bool
    {
        return $this === self::ThangAm;
    }

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::Thang => 'Hằng tháng',
            self::ThangAm => 'Hằng tháng âm lịch',
        };
    }
}
