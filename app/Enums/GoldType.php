<?php

declare(strict_types=1);

namespace App\Enums;

enum GoldType: string
{
    case Sjc = 'sjc';
    case Nhan9999 = 'nhan_9999';
    case Vang24k = 'vang_24k';
    case Vang18k = 'vang_18k';
    case Vang14k = 'vang_14k';
    case Vang10k = 'vang_10k';
    case Khac = 'khac';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sjc => 'SJC',
            self::Nhan9999 => 'Nhẫn trơn 9999',
            self::Vang24k => 'Vàng 24K',
            self::Vang18k => 'Vàng 18K',
            self::Vang14k => 'Vàng 14K',
            self::Vang10k => 'Vàng 10K',
            self::Khac => 'Loại khác',
        };
    }
}
