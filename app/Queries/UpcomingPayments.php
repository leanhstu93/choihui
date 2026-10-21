<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\HuiCalculator;
use App\Domain\HuiSchedule;
use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\User;
use DateTimeImmutable;

/**
 * Các khoản mình phải đóng đến một ngày (FR-08, FR-09), gồm cả khoản đã quá hạn.
 *
 * Kỳ đã nhập lãi thăm: lấy số tiền đã tính trong cash_entries. Kỳ chưa nhập lãi:
 * ước tính bằng lãi thăm của kỳ gần nhất đã mở (đánh dấu isEstimate).
 *
 * Lọc user_id tường minh thay vì dựa vào global scope, vì lệnh gửi email nhắc
 * chạy không có ai đăng nhập (document/01-dac-ta.md mục 8, ca test 5).
 */
final class UpcomingPayments
{
    /**
     * @return list<UpcomingPayment> Sắp theo hạn đóng tăng dần.
     */
    public function forUser(User $user, DateTimeImmutable $until): array
    {
        $groups = HuiGroup::query()
            ->withoutGlobalScope(HuiGroup::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->where('status', HuiGroupStatus::DangChay)
            ->with([
                'slots' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds.cashEntries' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds.cashEntries.slot' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
            ])
            ->get();

        $payments = [];

        foreach ($groups as $group) {
            array_push($payments, ...$this->paymentsOfGroup($group, $until));
        }

        usort($payments, fn (UpcomingPayment $a, UpcomingPayment $b): int => [$a->dueDate, $a->groupName, $a->slotLabel] <=> [$b->dueDate, $b->groupName, $b->slotLabel]);

        return $payments;
    }

    /**
     * @return list<UpcomingPayment>
     */
    private function paymentsOfGroup(HuiGroup $group, DateTimeImmutable $until): array
    {
        $latestBid = HuiCalculator::latestBid($group->rounds->pluck('bid_amount', 'round_no')->all());
        $wonRoundBySlot = $group->rounds
            ->filter(fn (HuiRound $round): bool => $round->winner_type === WinnerType::Minh)
            ->mapWithKeys(fn (HuiRound $round): array => [(string) $round->winner_slot_id => $round->round_no])
            ->all();

        $payments = [];

        foreach ($group->rounds as $round) {
            $dueDate = HuiSchedule::dueDate($round->open_date->toDateTimeImmutable(), $group->due_offset_days);

            if ($dueDate > $until) {
                continue;
            }

            if ($round->bid_amount !== null) {
                foreach ($round->cashEntries as $entry) {
                    if ($entry->direction === CashDirection::Dong && $entry->status === CashEntryStatus::Chua) {
                        $payments[] = new UpcomingPayment($group->id, $group->name, $round->id, $round->round_no, $entry->slot->label, $dueDate, $entry->amount_expected, false, $entry->id);
                    }
                }

                continue;
            }

            foreach ($group->slots as $slot) {
                $wonRoundNo = $wonRoundBySlot[$slot->id] ?? null;
                $amount = HuiCalculator::amountDue($round->round_no, $group->face_value, min($latestBid, $group->face_value), $wonRoundNo);
                $payments[] = new UpcomingPayment($group->id, $group->name, $round->id, $round->round_no, $slot->label, $dueDate, $amount, true, null);
            }
        }

        return $payments;
    }
}
