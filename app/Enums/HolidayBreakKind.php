<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Các đợt nghỉ lễ hưởng nguyên lương trong năm (Bộ luật Lao động 2019 Điều 112 và Nghị quyết 28/2026/QH16).
 */
enum HolidayBreakKind: string
{
    case TetDuongLich = 'tet_duong_lich';
    case TetNguyenDan = 'tet_nguyen_dan';
    case GioTo = 'gio_to';
    case ThongNhat = 'thong_nhat';
    case QuocKhanh = 'quoc_khanh';
    case VanHoa = 'van_hoa';

    public function label(): string
    {
        return match ($this) {
            self::TetDuongLich => 'Tết Dương lịch',
            self::TetNguyenDan => 'Tết Nguyên đán',
            self::GioTo => 'Giỗ Tổ Hùng Vương',
            self::ThongNhat => 'Lễ 30/4 và 1/5',
            self::QuocKhanh => 'Quốc khánh',
            self::VanHoa => 'Ngày Văn hóa Việt Nam',
        };
    }

    /**
     * Năm đầu tiên có đợt nghỉ này: 24/11 được nghỉ từ năm 2026.
     */
    public function sinceYear(): int
    {
        return $this === self::VanHoa ? 2026 : 1;
    }
}
