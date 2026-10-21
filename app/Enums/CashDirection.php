<?php

declare(strict_types=1);

namespace App\Enums;

enum CashDirection: string
{
    case Dong = 'dong';
    case Nhan = 'nhan';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::Dong => 'Đóng',
            self::Nhan => 'Nhận',
        };
    }
}
