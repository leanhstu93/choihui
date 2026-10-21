<?php

declare(strict_types=1);

namespace App\Enums;

enum HuiFrequency: string
{
    case Thang = 'thang';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::Thang => 'Hằng tháng',
        };
    }
}
