<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Can chi của năm, tháng, ngày âm lịch và giờ hoàng đạo (thuật toán Hồ Ngọc Đức). PHP thuần.
 */
final class CanChi
{
    public const CAN = ['Giáp', 'Ất', 'Bính', 'Đinh', 'Mậu', 'Kỷ', 'Canh', 'Tân', 'Nhâm', 'Quý'];

    public const CHI = ['Tý', 'Sửu', 'Dần', 'Mão', 'Thìn', 'Tỵ', 'Ngọ', 'Mùi', 'Thân', 'Dậu', 'Tuất', 'Hợi'];

    public const CHI_ICON = ['🐭', '🐃', '🐯', '🐱', '🐲', '🐍', '🐴', '🐐', '🐵', '🐔', '🐶', '🐷'];

    /** Giờ hoàng đạo theo chi của ngày (Tý/Ngọ, Sửu/Mùi, …): 1 là giờ tốt, từ giờ Tý tới giờ Hợi. */
    private const LUCKY_HOURS = ['110100101100', '001101001011', '110011010010', '101100110100', '001011001101', '010010110011'];

    public static function yearCan(int $lunarYear): int
    {
        return ($lunarYear + 6) % 10;
    }

    public static function yearChi(int $lunarYear): int
    {
        return ($lunarYear + 8) % 12;
    }

    /**
     * Ví dụ 2027 → "Đinh Mùi".
     */
    public static function yearName(int $lunarYear): string
    {
        return self::CAN[self::yearCan($lunarYear)].' '.self::CHI[self::yearChi($lunarYear)];
    }

    /**
     * Tháng nhuận dùng can chi của tháng thường cùng số. Ví dụ tháng Giêng năm Bính Ngọ → "Canh Dần".
     */
    public static function monthName(int $lunarMonth, int $lunarYear): string
    {
        return self::CAN[self::monthCan($lunarMonth, $lunarYear)].' '.self::CHI[self::monthChi($lunarMonth)];
    }

    public static function monthCan(int $lunarMonth, int $lunarYear): int
    {
        return ($lunarYear * 12 + $lunarMonth + 3) % 10;
    }

    public static function monthChi(int $lunarMonth): int
    {
        return ($lunarMonth + 1) % 12;
    }

    public static function dayCan(int $julianDay): int
    {
        return ($julianDay + 9) % 10;
    }

    public static function dayChi(int $julianDay): int
    {
        return ($julianDay + 1) % 12;
    }

    /**
     * Chi của các giờ hoàng đạo trong ngày có chi $dayChi (6 giờ).
     *
     * @return list<int>
     */
    public static function luckyHours(int $dayChi): array
    {
        $pattern = self::LUCKY_HOURS[$dayChi % 6];

        return array_values(array_filter(range(0, 11), fn (int $hour): bool => $pattern[$hour] === '1'));
    }

    /**
     * Khung giờ của chi $hourChi, ví dụ Tý → "23–1h".
     */
    public static function hourRange(int $hourChi): string
    {
        return sprintf('%d–%dh', (23 + $hourChi * 2) % 24, (1 + $hourChi * 2) % 24);
    }
}
