<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Domain\HuiSchedule;
use App\Domain\PaymentStatus;
use App\Enums\CashDirection;
use App\Exceptions\HuiActionException;
use App\Models\CashEntry;
use DateTimeInterface;

/**
 * Ghi nhận số tiền mình thực đóng cho một kỳ (FR-06). Đóng 0 đồng nghĩa là bỏ đánh dấu.
 */
final class MarkPaid
{
    public function handle(CashEntry $entry, int $amount, ?DateTimeInterface $paidDate, ?string $note = null): CashEntry
    {
        if ($entry->direction !== CashDirection::Dong) {
            throw new HuiActionException('Đây là khoản nhận, hãy dùng ghi nhận tiền nhận.');
        }

        if ($amount < 0) {
            throw new HuiActionException('Số tiền đóng không được âm.');
        }

        if ($amount > 0 && $paidDate === null) {
            throw new HuiActionException('Cần nhập ngày đóng.');
        }

        $round = $entry->round;
        $paidDate = $amount > 0 ? $paidDate : null;

        $entry->update([
            'amount_actual' => $amount,
            'paid_date' => $paidDate,
            'note' => $note ?? $entry->note,
            'status' => PaymentStatus::forPayment(
                $entry->amount_expected,
                $amount,
                $paidDate,
                HuiSchedule::dueDate($round->open_date->toDateTimeImmutable(), $round->group->due_offset_days),
            ),
        ]);

        return $entry;
    }
}
