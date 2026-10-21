<?php

declare(strict_types=1);

namespace App\Domain;

use InvalidArgumentException;

/**
 * Sổ thu / chi của chủ hụi (document/04-chu-hui.md mục 5). PHP thuần, dùng lại
 * công thức của HuiCalculator; mọi số tiền là số nguyên theo đồng.
 */
final class HostLedger
{
    /**
     * Khoản phải thu của từng tay và khoản phải chi cho tay hốt ở kỳ k.
     *
     * @param  array<int, int|null>  $wonRoundBySlot  slot_no => kỳ tay đó đã hốt (null = chưa hốt), đủ N tay
     * @return array{collections: array<int, int>, payout: int}
     */
    public static function roundEntries(int $roundNo, int $totalSlots, int $faceValue, int $bidAmount, int $commission, array $wonRoundBySlot, int $winnerSlotNo): array
    {
        if (count($wonRoundBySlot) !== $totalSlots) {
            throw new InvalidArgumentException("Dây phải có đủ {$totalSlots} tay.");
        }

        if (! array_key_exists($winnerSlotNo, $wonRoundBySlot)) {
            throw new InvalidArgumentException("Tay {$winnerSlotNo} không thuộc dây.");
        }

        $winnerWonRound = $wonRoundBySlot[$winnerSlotNo];

        if ($winnerWonRound !== null && $winnerWonRound !== $roundNo) {
            throw new InvalidArgumentException("Tay {$winnerSlotNo} đã hốt ở kỳ {$winnerWonRound}.");
        }

        $collections = [];

        foreach ($wonRoundBySlot as $slotNo => $wonRoundNo) {
            if ($slotNo === $winnerSlotNo) {
                continue;
            }

            $collections[$slotNo] = HuiCalculator::amountDue($roundNo, $faceValue, $bidAmount, $wonRoundNo === $roundNo ? null : $wonRoundNo);
        }

        return [
            'collections' => $collections,
            'payout' => HuiCalculator::netPayout($roundNo, $totalSlots, $faceValue, $bidAmount, $commission),
        ];
    }

    /**
     * Tiền chủ hụi tự bỏ ra để chi đủ cho người hốt khi thu chưa đủ (C5).
     */
    public static function topUp(int $collected, int $paidOut): int
    {
        return max(0, $paidOut - $collected);
    }

    /**
     * Số tiền hụi viên còn nợ: cộng phần thiếu của từng khoản, đóng dư một khoản
     * không bù cho khoản khác.
     *
     * @param  list<array{expected: int, actual: int}>  $entries  Các khoản phải thu chưa đánh dấu bùng.
     */
    public static function debt(array $entries): int
    {
        return array_sum(array_map(
            fn (array $entry): int => max(0, $entry['expected'] - $entry['actual']),
            $entries,
        ));
    }

    /**
     * Lời/lỗ của chủ hụi trên một dây.
     *
     * Thực tế = đã thu − đã chi. Dự kiến cộng thêm nợ còn đòi và tiền thảo của
     * các kỳ chưa mở; nợ đã đánh dấu bùng không cộng vì chủ hụi đã mất hẳn.
     *
     * @return array{actual: int, projected: int}
     */
    public static function hostProfit(int $collected, int $paidOut, int $collectibleDebt, int $commission, int $unopenedRounds): array
    {
        $actual = $collected - $paidOut;

        return [
            'actual' => $actual,
            'projected' => $actual + $collectibleDebt + $commission * $unopenedRounds,
        ];
    }
}
