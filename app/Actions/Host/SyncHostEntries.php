<?php

declare(strict_types=1);

namespace App\Actions\Host;

use App\Domain\HostLedger;
use App\Domain\HuiSchedule;
use App\Domain\PaymentStatus;
use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Enums\HuiGroupStatus;
use App\Models\HostEntry;
use App\Models\HostSlot;
use App\Models\HuiGroup;
use App\Models\HuiRound;

/**
 * Đưa các khoản thu / chi của dây chủ hụi về đúng với kết quả các kỳ
 * (document/04-chu-hui.md mục 5). Nơi duy nhất sinh host_entries.
 *
 * Kỳ đã có lãi thăm và tay hốt: một khoản "thu" cho mỗi tay khác, một khoản "chi"
 * cho tay hốt. Kỳ chưa có kết quả không có khoản nào. Dây bể ở kỳ b thì từ kỳ b
 * không còn khoản mới; các khoản đã có tiền giữ lại làm lịch sử (C7).
 *
 * Khoản chưa có tiền được cập nhật thẳng. Khoản đã có tiền chỉ được sửa khi
 * $overwritePaid = true; nếu không, hàm trả về các khoản đó để người gọi hỏi lại
 * người dùng (và rollback transaction). Dây không có host_slots (dây hụi viên)
 * thì không làm gì.
 */
final class SyncHostEntries
{
    /**
     * @return list<HostEntry> Các khoản đã có tiền cần thay đổi nhưng chưa được phép sửa.
     */
    public function handle(HuiGroup $group, bool $overwritePaid = false): array
    {
        $group->load(['hostSlots', 'rounds.hostEntries']);

        if ($group->hostSlots->isEmpty()) {
            return [];
        }

        $slotIdByNo = $group->hostSlots->pluck('id', 'slot_no')->all();
        $slotNoById = array_flip($slotIdByNo);
        $wonRoundBySlotNo = $group->hostSlots
            ->mapWithKeys(fn (HostSlot $slot): array => [$slot->slot_no => null])
            ->all();

        foreach ($group->rounds as $round) {
            if ($round->winner_host_slot_id !== null) {
                $wonRoundBySlotNo[$slotNoById[$round->winner_host_slot_id]] = $round->round_no;
            }
        }

        $conflicts = [];

        foreach ($group->rounds as $round) {
            $planned = $this->plannedEntries($group, $round, $wonRoundBySlotNo, $slotIdByNo, $slotNoById);
            $existing = $round->hostEntries->keyBy('host_slot_id');

            foreach ($existing as $slotId => $entry) {
                $plan = $planned[$slotId] ?? null;

                if ($plan === null) {
                    if (! $this->hasPayment($entry)) {
                        $entry->delete();
                    } elseif ($this->isAfterBreak($group, $round)) {
                        // Dây bể: giữ lại các khoản đã có tiền làm lịch sử.
                    } elseif ($overwritePaid) {
                        $entry->delete();
                    } else {
                        $conflicts[] = $entry;
                    }

                    continue;
                }

                if ($entry->direction === $plan['direction'] && $entry->amount_expected === $plan['amount_expected']) {
                    continue;
                }

                if (! $this->hasPayment($entry)) {
                    $entry->update($plan);

                    continue;
                }

                if (! $overwritePaid) {
                    $conflicts[] = $entry;

                    continue;
                }

                $entry->fill($plan);
                $entry->status = $this->resolveStatus($group, $round, $entry);
                $entry->save();
            }

            foreach (array_diff_key($planned, $existing->all()) as $slotId => $plan) {
                $round->hostEntries()->create(['host_slot_id' => $slotId] + $plan);
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<int, int|null>  $wonRoundBySlotNo
     * @param  array<int, string>  $slotIdByNo
     * @param  array<string, int>  $slotNoById
     * @return array<string, array{direction: HostDirection, amount_expected: int}>
     */
    private function plannedEntries(HuiGroup $group, HuiRound $round, array $wonRoundBySlotNo, array $slotIdByNo, array $slotNoById): array
    {
        if ($round->bid_amount === null || $round->winner_host_slot_id === null || $this->isAfterBreak($group, $round)) {
            return [];
        }

        $winnerSlotNo = $slotNoById[$round->winner_host_slot_id];
        $entries = HostLedger::roundEntries($round->round_no, $group->total_slots, $group->face_value, $round->bid_amount, $group->commission_amount, $wonRoundBySlotNo, $winnerSlotNo);

        $planned = [$slotIdByNo[$winnerSlotNo] => ['direction' => HostDirection::Chi, 'amount_expected' => $entries['payout']]];

        foreach ($entries['collections'] as $slotNo => $amount) {
            $planned[$slotIdByNo[$slotNo]] = ['direction' => HostDirection::Thu, 'amount_expected' => $amount];
        }

        return $planned;
    }

    private function isAfterBreak(HuiGroup $group, HuiRound $round): bool
    {
        return $group->status === HuiGroupStatus::Be
            && $group->broken_round_no !== null
            && $round->round_no >= $group->broken_round_no;
    }

    private function hasPayment(HostEntry $entry): bool
    {
        return $entry->amount_actual > 0 || $entry->status !== CashEntryStatus::Chua;
    }

    private function resolveStatus(HuiGroup $group, HuiRound $round, HostEntry $entry): CashEntryStatus
    {
        if ($entry->status === CashEntryStatus::Bung && $entry->amount_actual < $entry->amount_expected) {
            return CashEntryStatus::Bung;
        }

        return PaymentStatus::forPayment(
            $entry->amount_expected,
            $entry->amount_actual,
            $entry->paid_date?->toDateTimeImmutable(),
            HuiSchedule::dueDate($round->open_date->toDateTimeImmutable(), $group->due_offset_days),
        );
    }
}
