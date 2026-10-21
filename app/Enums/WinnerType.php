<?php

declare(strict_types=1);

namespace App\Enums;

enum WinnerType: string
{
    case ChuaCo = 'chua_co';
    case Minh = 'minh';
    case NguoiKhac = 'nguoi_khac';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::ChuaCo => 'Chưa có',
            self::Minh => 'Mình',
            self::NguoiKhac => 'Người khác',
        };
    }
}
