<?php

declare(strict_types=1);

namespace App\Actions\Host;

use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Models\HostEntry;
use App\Models\HuiRound;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * "Thu đủ tất cả" (CH-06): mọi khoản thu chưa đánh dấu của kỳ được ghi đủ tiền
 * vào ngày đã cho. Khoản đã ghi (đủ, thiếu, bùng...) giữ nguyên.
 */
final class MarkAllCollected
{
    public function __construct(private MarkCollected $markCollected) {}

    /**
     * @return int Số khoản vừa được đánh dấu.
     */
    public function handle(HuiRound $round, DateTimeInterface $paidDate): int
    {
        $entries = $round->hostEntries()
            ->where('direction', HostDirection::Thu)
            ->where('status', CashEntryStatus::Chua)
            ->get();

        DB::transaction(function () use ($entries, $paidDate): void {
            $entries->each(fn (HostEntry $entry) => $this->markCollected->handle($entry, $entry->amount_expected, $paidDate));
        });

        return $entries->count();
    }
}
