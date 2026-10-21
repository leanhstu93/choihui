<?php

declare(strict_types=1);

namespace App\Enums;

enum HuiGroupType: string
{
    case CoLai = 'co_lai';
    case KhongLai = 'khong_lai';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::CoLai => 'Có lãi',
            self::KhongLai => 'Không lãi',
        };
    }
}
