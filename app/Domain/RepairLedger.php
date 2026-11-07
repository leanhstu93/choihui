<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeInterface;

/**
 * Quy tắc độ bền của sổ sửa chữa (document/08-sua-chua.md mục 4).
 */
final class RepairLedger
{
    /**
     * Khóa so sánh hạng mục: không phân biệt hoa thường và khoảng trắng thừa.
     */
    public static function partKey(string $part): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($part)));
    }

    /**
     * Khoảng thời gian dễ đọc: "2 năm 3 tháng", "5 tháng", "12 ngày". Null khi không có
     * khoảng nào (cùng ngày) hoặc ngày sau đứng trước ngày đầu.
     */
    public static function duration(DateTimeInterface $from, DateTimeInterface $to): ?string
    {
        $gap = $from->diff($to);

        if ($gap->invert === 1) {
            return null;
        }

        if ($gap->y > 0) {
            return $gap->m > 0 ? "{$gap->y} năm {$gap->m} tháng" : "{$gap->y} năm";
        }

        if ($gap->m > 0) {
            return "{$gap->m} tháng";
        }

        return $gap->d > 0 ? "{$gap->d} ngày" : null;
    }

    /**
     * Số km đi được giữa hai lần. Null khi thiếu số km, hoặc số sau không lớn hơn số trước
     * (nhập nhầm, thay đồng hồ).
     */
    public static function distance(?int $fromKm, ?int $toKm): ?int
    {
        if ($fromKm === null || $toKm === null || $toKm <= $fromKm) {
            return null;
        }

        return $toKm - $fromKm;
    }
}
