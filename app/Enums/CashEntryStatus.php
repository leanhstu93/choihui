<?php

declare(strict_types=1);

namespace App\Enums;

enum CashEntryStatus: string
{
    case Chua = 'chua';
    case Du = 'du';
    case Thieu = 'thieu';
    case Muon = 'muon';
    case ConNo = 'con_no';
    case Bung = 'bung';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::Chua => 'Chưa',
            self::Du => 'Đủ',
            self::Thieu => 'Thiếu',
            self::Muon => 'Muộn',
            self::ConNo => 'Còn nợ',
            self::Bung => 'Bùng',
        };
    }
}
