<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Công thức sổ vàng (document/06-vang.md mục 4). Khối lượng là int theo ly
 * (1 chỉ = 100 ly), tiền là int theo đồng, giá là đồng / chỉ.
 */
final class GoldLedger
{
    public const LY_PER_CHI = 100;

    public static function remainingWeight(int $weight, int $soldWeight): int
    {
        return $weight - $soldWeight;
    }

    /**
     * Vốn của phần đã bán, chia theo khối lượng (V9).
     */
    public static function soldCost(int $cost, int $weight, int $soldWeight): int
    {
        return self::divideRounded($cost * $soldWeight, $weight);
    }

    /**
     * Lấy vốn trừ vốn đã bán thay vì chia lần nữa, để hai phần luôn cộng lại đúng vốn.
     */
    public static function remainingCost(int $cost, int $weight, int $soldWeight): int
    {
        return $cost - self::soldCost($cost, $weight, $soldWeight);
    }

    public static function value(int $weight, int $pricePerChi): int
    {
        return self::divideRounded($weight * $pricePerChi, self::LY_PER_CHI);
    }

    public static function unrealizedProfit(int $cost, int $weight, int $soldWeight, int $pricePerChi): int
    {
        return self::value(self::remainingWeight($weight, $soldWeight), $pricePerChi)
            - self::remainingCost($cost, $weight, $soldWeight);
    }

    public static function realizedProfit(int $cost, int $weight, int $soldWeight, int $soldAmount): int
    {
        return $soldAmount - self::soldCost($cost, $weight, $soldWeight);
    }

    /**
     * Đổi chuỗi người dùng nhập ("1,25", "1.25", "2") sang ly. Trả null nếu không hợp lệ.
     */
    public static function parseChi(string $input): ?int
    {
        if (preg_match('/^(\d{1,5})(?:[.,](\d{1,2}))?$/', trim($input), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1] * self::LY_PER_CHI + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    /**
     * Số chỉ kiểu Việt Nam, bỏ số 0 thừa: 125 → "1,25", 250 → "2,5", 300 → "3".
     */
    public static function formatChi(int $weight): string
    {
        $whole = number_format(intdiv($weight, self::LY_PER_CHI), 0, ',', '.');
        $fraction = rtrim(str_pad((string) ($weight % self::LY_PER_CHI), 2, '0', STR_PAD_LEFT), '0');

        return $fraction === '' ? $whole : "{$whole},{$fraction}";
    }

    /**
     * Làm tròn đến đồng bằng số nguyên, không qua float.
     */
    private static function divideRounded(int $dividend, int $divisor): int
    {
        return intdiv($dividend * 2 + $divisor, $divisor * 2);
    }
}
