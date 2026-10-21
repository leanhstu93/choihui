<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Công thức tiền hụi (document/01-dac-ta.md mục 5). PHP thuần, mọi số tiền là
 * số nguyên theo đồng.
 *
 * Ký hiệu: M mệnh giá, N số tay (= số kỳ), k số thứ tự kỳ, L lãi thăm của kỳ.
 */
final class HuiCalculator
{
    /**
     * Số tiền một tay phải đóng ở kỳ k.
     *
     * @param  int|null  $wonRoundNo  Kỳ mà tay này đã hốt, null nếu chưa hốt.
     */
    public static function amountDue(int $roundNo, int $faceValue, int $bidAmount, ?int $wonRoundNo): int
    {
        self::assertMoney($faceValue, $bidAmount);

        if ($wonRoundNo === $roundNo) {
            return 0;
        }

        if ($wonRoundNo !== null && $wonRoundNo < $roundNo) {
            return $faceValue;
        }

        return $faceValue - $bidAmount;
    }

    /**
     * Tiền nhận khi hốt ở kỳ k, chưa trừ thảo: (k − 1) × M + (N − k) × (M − L).
     */
    public static function payout(int $roundNo, int $totalSlots, int $faceValue, int $bidAmount): int
    {
        self::assertRound($roundNo, $totalSlots);
        self::assertMoney($faceValue, $bidAmount);

        return ($roundNo - 1) * $faceValue + ($totalSlots - $roundNo) * ($faceValue - $bidAmount);
    }

    /**
     * Tiền thực nhận khi hốt ở kỳ k, đã trừ tiền thảo đưa chủ hụi.
     */
    public static function netPayout(int $roundNo, int $totalSlots, int $faceValue, int $bidAmount, int $commission): int
    {
        if ($commission < 0) {
            throw new InvalidArgumentException('Tiền thảo không được âm.');
        }

        $payout = self::payout($roundNo, $totalSlots, $faceValue, $bidAmount);

        if ($commission > $payout) {
            throw new InvalidArgumentException('Tiền thảo lớn hơn số tiền hốt được.');
        }

        return $payout - $commission;
    }

    /**
     * Lời (dương) hoặc lỗ (âm) theo tiền thực tế.
     */
    public static function profit(int $totalReceived, int $totalPaid): int
    {
        return $totalReceived - $totalPaid;
    }

    /**
     * Lãi thăm trung bình của các kỳ đã nhập lãi, làm tròn về đồng; 0 nếu chưa có kỳ nào.
     *
     * @param  array<int, int|null>  $bidsByRound  round_no => lãi thăm (null = chưa mở)
     */
    public static function averageBid(array $bidsByRound): int
    {
        $openedBids = array_filter($bidsByRound, fn (?int $bid): bool => $bid !== null);

        if ($openedBids === []) {
            return 0;
        }

        return (int) round(array_sum($openedBids) / count($openedBids));
    }

    /**
     * Lời/lỗ dự kiến của một tay khi dây chạy hết: các kỳ chưa mở lấy lãi trung
     * bình các kỳ đã mở.
     *
     * @param  array<int, int|null>  $bidsByRound  round_no => lãi thăm (null = chưa mở)
     * @param  int  $winRoundNo  Kỳ tay này hốt (thực tế, hoặc giả định cho tay chưa hốt).
     */
    public static function projectedProfit(int $totalSlots, int $faceValue, int $commission, array $bidsByRound, int $winRoundNo): int
    {
        self::assertRound($winRoundNo, $totalSlots);

        $averageBid = self::averageBid($bidsByRound);
        $totalPaid = 0;

        for ($roundNo = 1; $roundNo <= $totalSlots; $roundNo++) {
            $bid = $bidsByRound[$roundNo] ?? $averageBid;

            if ($roundNo === $winRoundNo) {
                $received = self::netPayout($roundNo, $totalSlots, $faceValue, $bid, $commission);

                continue;
            }

            $totalPaid += self::amountDue($roundNo, $faceValue, $bid, $winRoundNo);
        }

        return self::profit($received ?? 0, $totalPaid);
    }

    /**
     * Kỳ giả định cho các tay chưa hốt: lấy các kỳ còn trống từ cuối dây trở về.
     *
     * @param  list<int>  $takenRoundNos  Các kỳ đã có người hốt (mình hoặc người khác).
     * @return list<int> Kỳ giả định, theo thứ tự tay chưa hốt.
     */
    public static function assumedWinRounds(int $totalSlots, array $takenRoundNos, int $unwonSlotCount): array
    {
        $freeRoundNos = array_values(array_diff(range($totalSlots, 1), $takenRoundNos));

        if ($unwonSlotCount > count($freeRoundNos)) {
            throw new InvalidArgumentException('Số tay chưa hốt nhiều hơn số kỳ còn trống.');
        }

        return array_slice($freeRoundNos, 0, $unwonSlotCount);
    }

    private static function assertRound(int $roundNo, int $totalSlots): void
    {
        if ($totalSlots < 2) {
            throw new InvalidArgumentException('Dây hụi phải có ít nhất 2 tay.');
        }

        if ($roundNo < 1 || $roundNo > $totalSlots) {
            throw new InvalidArgumentException("Kỳ {$roundNo} nằm ngoài 1..{$totalSlots}.");
        }
    }

    private static function assertMoney(int $faceValue, int $bidAmount): void
    {
        if ($faceValue <= 0) {
            throw new InvalidArgumentException('Mệnh giá phải lớn hơn 0.');
        }

        if ($bidAmount < 0 || $bidAmount > $faceValue) {
            throw new InvalidArgumentException('Lãi thăm phải từ 0 đến mệnh giá.');
        }
    }
}
