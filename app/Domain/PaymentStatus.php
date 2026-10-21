<?php

declare(strict_types=1);

namespace App\Domain;

use App\Enums\CashEntryStatus;
use DateTimeInterface;

/**
 * Trạng thái của một dòng tiền theo số tiền thực tế (document/01-dac-ta.md mục 6).
 */
final class PaymentStatus
{
    /**
     * Khoản mình đóng: chưa đóng, đóng thiếu, đủ nhưng trễ hạn, hoặc đủ đúng hạn.
     */
    public static function forPayment(int $expected, int $actual, ?DateTimeInterface $paidDate, DateTimeInterface $dueDate): CashEntryStatus
    {
        if ($actual <= 0) {
            return CashEntryStatus::Chua;
        }

        if ($actual < $expected) {
            return CashEntryStatus::Thieu;
        }

        if ($paidDate !== null && $paidDate->format('Y-m-d') > $dueDate->format('Y-m-d')) {
            return CashEntryStatus::Muon;
        }

        return CashEntryStatus::Du;
    }

    /**
     * Khoản mình nhận khi hốt: nhận đủ, còn nợ, hoặc phần thiếu không đòi được (bùng).
     */
    public static function forReceipt(int $expected, int $actual, bool $writtenOff): CashEntryStatus
    {
        if ($actual >= $expected && $actual > 0) {
            return CashEntryStatus::Du;
        }

        if ($writtenOff) {
            return CashEntryStatus::Bung;
        }

        return $actual > 0 ? CashEntryStatus::ConNo : CashEntryStatus::Chua;
    }
}
