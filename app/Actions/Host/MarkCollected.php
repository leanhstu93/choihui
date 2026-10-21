<?php

declare(strict_types=1);

namespace App\Actions\Host;

use App\Domain\HuiSchedule;
use App\Domain\PaymentStatus;
use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Exceptions\HuiActionException;
use App\Models\HostEntry;
use DateTimeInterface;

/**
 * Ghi nhận số tiền thực thu từ một tay (CH-06). Thu 0 đồng nghĩa là bỏ đánh dấu.
 * $writtenOff = true khi phần còn thiếu không đòi được nữa (bùng): không còn tính
 * vào nợ, chủ hụi chịu mất.
 */
final class MarkCollected
{
    public function handle(HostEntry $entry, int $amount, ?DateTimeInterface $paidDate, ?string $note = null, bool $writtenOff = false): HostEntry
    {
        if ($entry->direction !== HostDirection::Thu) {
            throw new HuiActionException('Đây là khoản chi, hãy dùng ghi nhận tiền chi.');
        }

        if ($amount < 0) {
            throw new HuiActionException('Số tiền thu không được âm.');
        }

        if ($amount > 0 && $paidDate === null) {
            throw new HuiActionException('Cần nhập ngày thu.');
        }

        $round = $entry->round;
        $paidDate = $amount > 0 ? $paidDate : null;
        $status = $writtenOff && $amount < $entry->amount_expected
            ? CashEntryStatus::Bung
            : PaymentStatus::forPayment(
                $entry->amount_expected,
                $amount,
                $paidDate,
                HuiSchedule::dueDate($round->open_date->toDateTimeImmutable(), $round->group->due_offset_days),
            );

        $entry->update([
            'amount_actual' => $amount,
            'paid_date' => $paidDate,
            'note' => $note ?? $entry->note,
            'status' => $status,
        ]);

        return $entry;
    }
}
