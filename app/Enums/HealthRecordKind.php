<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Loại lần khám trong sổ sức khỏe (document/09-gia-dinh.md G9).
 */
enum HealthRecordKind: string
{
    case Kham = 'kham';
    case Tiem = 'tiem';
    case XetNghiem = 'xet_nghiem';
    case Khac = 'khac';

    public function label(): string
    {
        return match ($this) {
            self::Kham => 'Khám bệnh',
            self::Tiem => 'Tiêm chủng',
            self::XetNghiem => 'Xét nghiệm',
            self::Khac => 'Khác',
        };
    }
}
