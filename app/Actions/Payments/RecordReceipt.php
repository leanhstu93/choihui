<?php

declare(strict_types=1);

namespace App\Actions\Payments;

use App\Domain\PaymentStatus;
use App\Enums\CashDirection;
use App\Exceptions\HuiActionException;
use App\Models\CashEntry;
use DateTimeInterface;

/**
 * Ghi nhận số tiền mình thực nhận khi hốt (FR-07). Nhận thiếu thì "còn nợ", hoặc
 * "bùng" khi $writtenOff = true (phần thiếu không đòi được, FR-17).
 */
final class RecordReceipt
{
    public function handle(CashEntry $entry, int $amount, ?DateTimeInterface $receivedDate, bool $writtenOff = false, ?string $note = null): CashEntry
    {
        if ($entry->direction !== CashDirection::Nhan) {
            throw new HuiActionException('Đây là khoản đóng, hãy dùng đánh dấu đã đóng.');
        }

        if ($amount < 0) {
            throw new HuiActionException('Số tiền nhận không được âm.');
        }

        if ($amount > 0 && $receivedDate === null) {
            throw new HuiActionException('Cần nhập ngày nhận.');
        }

        $entry->update([
            'amount_actual' => $amount,
            'paid_date' => $amount > 0 ? $receivedDate : null,
            'note' => $note ?? $entry->note,
            'status' => PaymentStatus::forReceipt($entry->amount_expected, $amount, $writtenOff),
        ]);

        return $entry;
    }
}
