<?php

declare(strict_types=1);

namespace App\Actions\Host;

use App\Domain\HuiSchedule;
use App\Domain\PaymentStatus;
use App\Enums\HostDirection;
use App\Exceptions\HuiActionException;
use App\Models\HostEntry;
use DateTimeInterface;

/**
 * Ghi nhận số tiền thực chi cho tay hốt (CH-07). Chi 0 đồng nghĩa là bỏ đánh dấu.
 * Chủ hụi chi đủ kể cả khi chưa thu đủ; phần chênh là tiền bù (C5).
 */
final class MarkPaidOut
{
    public function handle(HostEntry $entry, int $amount, ?DateTimeInterface $paidDate, ?string $note = null): HostEntry
    {
        if ($entry->direction !== HostDirection::Chi) {
            throw new HuiActionException('Đây là khoản thu, hãy dùng ghi nhận tiền thu.');
        }

        if ($amount < 0) {
            throw new HuiActionException('Số tiền chi không được âm.');
        }

        if ($amount > 0 && $paidDate === null) {
            throw new HuiActionException('Cần nhập ngày chi.');
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
