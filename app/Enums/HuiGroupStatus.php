<?php

declare(strict_types=1);

namespace App\Enums;

enum HuiGroupStatus: string
{
    case DangChay = 'dang_chay';
    case KetThuc = 'ket_thuc';
    case Be = 'be';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::DangChay => 'Đang chạy',
            self::KetThuc => 'Đã kết thúc',
            self::Be => 'Bể',
        };
    }
}
