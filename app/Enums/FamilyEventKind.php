<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại ngày nhớ (document/09-gia-dinh.md G2, G5).
 */
enum FamilyEventKind: string
{
    case Gio = 'gio';
    case SinhNhat = 'sinh_nhat';
    case KyNiem = 'ky_niem';
    case Khac = 'khac';

    public function label(): string
    {
        return match ($this) {
            self::Gio => 'Giỗ',
            self::SinhNhat => 'Sinh nhật',
            self::KyNiem => 'Kỷ niệm',
            self::Khac => 'Khác',
        };
    }

    /**
     * Cách đọc số năm tính từ năm gốc: "Tròn 60 tuổi", "Giỗ đầu", "Giỗ lần thứ 3", "5 năm".
     */
    public function yearsLabel(int $years): string
    {
        return match ($this) {
            self::SinhNhat => "Tròn {$years} tuổi",
            self::Gio => $years === 1 ? 'Giỗ đầu' : "Giỗ lần thứ {$years}",
            self::KyNiem, self::Khac => "{$years} năm",
        };
    }
}
