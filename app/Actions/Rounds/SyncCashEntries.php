<?php

declare(strict_types=1);

namespace App\Actions\Rounds;

use App\Domain\HuiCalculator;
use App\Domain\HuiSchedule;
use App\Domain\PaymentStatus;
use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;

/**
 * Đưa các dòng tiền của một dây về đúng với kết quả các kỳ (document/01-dac-ta.md mục 5).
 *
 * Mỗi kỳ đã nhập lãi thăm có một dòng cho mỗi tay của mình: "nhan" nếu tay đó hốt
 * kỳ này, "dong" nếu không. Kỳ chưa nhập lãi không có dòng nào. Dây bể ở kỳ b thì
 * từ kỳ b không còn khoản phải đóng, chỉ giữ khoản nhận của kỳ b.
 *
 * Dòng chưa có tiền thực tế được cập nhật thẳng. Dòng đã có tiền chỉ được sửa khi
 * $overwritePaid = true; nếu không, hàm trả về danh sách các dòng đó để người gọi
 * hỏi lại người dùng (và rollback transaction).
 */
final class SyncCashEntries
{
    /**
     * @return list<CashEntry> Các dòng đã có tiền cần thay đổi nhưng chưa được phép sửa.
     */
    public function handle(HuiGroup $group, bool $overwritePaid = false): array
    {
        $group->load(['slots', 'rounds.cashEntries']);

        /** @var array<string, int> $wonRoundBySlot */
        $wonRoundBySlot = $group->rounds
            ->filter(fn (HuiRound $round): bool => $round->winner_type === WinnerType::Minh)
            ->mapWithKeys(fn (HuiRound $round): array => [(string) $round->winner_slot_id => $round->round_no])
            ->all();

        $conflicts = [];

        foreach ($group->rounds as $round) {
            $planned = $this->plannedEntries($group, $round, $wonRoundBySlot);
            $existing = $round->cashEntries->keyBy('slot_id');

            foreach ($existing as $slotId => $entry) {
                $plan = $planned[$slotId] ?? null;

                if ($plan === null) {
                    if (! $this->hasPayment($entry)) {
                        $entry->delete();
                    } elseif ($this->isAfterBreak($group, $round)) {
                        // Dây bể: giữ lại các khoản đã đóng làm lịch sử.
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
                $round->cashEntries()->create(['slot_id' => $slotId] + $plan);
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<string, int>  $wonRoundBySlot
     * @return array<string, array{direction: CashDirection, amount_expected: int}>
     */
    private function plannedEntries(HuiGroup $group, HuiRound $round, array $wonRoundBySlot): array
    {
        if ($round->bid_amount === null) {
            return [];
        }

        $isAfterBreak = $this->isAfterBreak($group, $round);
        $planned = [];

        foreach ($group->slots as $slot) {
            $wonRoundNo = $wonRoundBySlot[$slot->id] ?? null;

            if ($wonRoundNo === $round->round_no) {
                if ($isAfterBreak && $round->round_no > $group->broken_round_no) {
                    continue;
                }

                $planned[$slot->id] = [
                    'direction' => CashDirection::Nhan,
                    'amount_expected' => HuiCalculator::netPayout($round->round_no, $group->total_slots, $group->face_value, $round->bid_amount, $group->commission_amount),
                ];

                continue;
            }

            if ($isAfterBreak) {
                continue;
            }

            $planned[$slot->id] = [
                'direction' => CashDirection::Dong,
                'amount_expected' => HuiCalculator::amountDue($round->round_no, $group->face_value, $round->bid_amount, $wonRoundNo),
            ];
        }

        return $planned;
    }

    private function isAfterBreak(HuiGroup $group, HuiRound $round): bool
    {
        return $group->status === HuiGroupStatus::Be
            && $group->broken_round_no !== null
            && $round->round_no >= $group->broken_round_no;
    }

    private function hasPayment(CashEntry $entry): bool
    {
        return $entry->amount_actual > 0 || $entry->status !== CashEntryStatus::Chua;
    }

    private function resolveStatus(HuiGroup $group, HuiRound $round, CashEntry $entry): CashEntryStatus
    {
        if ($entry->direction === CashDirection::Nhan) {
            return PaymentStatus::forReceipt($entry->amount_expected, $entry->amount_actual, $entry->status === CashEntryStatus::Bung);
        }

        return PaymentStatus::forPayment(
            $entry->amount_expected,
            $entry->amount_actual,
            $entry->paid_date?->toDateTimeImmutable(),
            HuiSchedule::dueDate($round->open_date->toDateTimeImmutable(), $group->due_offset_days),
        );
    }
}
