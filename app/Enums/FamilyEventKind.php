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

    /**
     * Biểu tượng hiện trên danh sách và nút chọn loại.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Gio => '🕯️',
            self::SinhNhat => '🎂',
            self::KyNiem => '💍',
            self::Khac => '📌',
        };
    }

    /**
     * Lịch hay dùng cho loại này: giỗ thường tính theo âm lịch, còn lại theo dương lịch.
     */
    public function defaultCalendar(): CalendarType
    {
        return $this === self::Gio ? CalendarType::Am : CalendarType::Duong;
    }

    /**
     * Tên ô năm gốc theo loại (G5).
     */
    public function originYearLabel(): string
    {
        return match ($this) {
            self::Gio => 'Năm mất',
            self::SinhNhat => 'Năm sinh',
            self::KyNiem, self::Khac => 'Năm bắt đầu',
        };
    }

    /**
     * Ví dụ tên gợi ý trong ô nhập.
     */
    public function titlePlaceholder(): string
    {
        return match ($this) {
            self::Gio => 'Ví dụ: Giỗ ông nội',
            self::SinhNhat => 'Ví dụ: Sinh nhật mẹ',
            self::KyNiem => 'Ví dụ: Kỷ niệm ngày cưới',
            self::Khac => 'Ví dụ: Rằm tháng Giêng',
        };
    }
}
