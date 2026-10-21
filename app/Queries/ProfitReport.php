<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\HuiCalculator;
use App\Enums\CashDirection;
use App\Enums\HuiGroupStatus;
use App\Enums\WinnerType;
use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\User;
use InvalidArgumentException;

/**
 * Báo cáo lời/lỗ từng dây và tổng (FR-10, document/01-dac-ta.md mục 5).
 *
 * Thực tế = tiền đã nhận − tiền đã đóng. Dự kiến (chỉ dây đang chạy) = tổng
 * projectedProfit của các tay, kỳ chưa mở lấy lãi trung bình, tay chưa hốt giả
 * định hốt các kỳ cuối còn trống.
 */
final class ProfitReport
{
    /**
     * @return array{groups: list<array{group: HuiGroup, paid: int, received: int, profit: int, projected: int|null}>, totals: array{paid: int, received: int, profit: int, projected: int}}
     */
    public function forUser(User $user): array
    {
        $groups = HuiGroup::query()
            ->withoutGlobalScope(HuiGroup::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->with([
                'slots' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds.cashEntries' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
            ])
            ->orderByRaw("status = 'dang_chay' desc")
            ->latest()
            ->get();

        $rows = [];
        $totals = ['paid' => 0, 'received' => 0, 'profit' => 0, 'projected' => 0];

        foreach ($groups as $group) {
            $row = $this->forGroup($group);
            $rows[] = $row;
            $totals['paid'] += $row['paid'];
            $totals['received'] += $row['received'];
            $totals['profit'] += $row['profit'];
            $totals['projected'] += $row['projected'] ?? $row['profit'];
        }

        return ['groups' => $rows, 'totals' => $totals];
    }

    /**
     * Lời/lỗ của một dây. Dây phải có sẵn slots, rounds.cashEntries (nạp nếu thiếu).
     *
     * @return array{group: HuiGroup, paid: int, received: int, profit: int, projected: int|null}
     */
    public function forGroup(HuiGroup $group): array
    {
        $group->loadMissing(['slots', 'rounds.cashEntries']);

        $entries = $group->rounds->flatMap(fn (HuiRound $round) => $round->cashEntries);
        $paid = (int) $entries->filter(fn (CashEntry $entry) => $entry->direction === CashDirection::Dong)->sum('amount_actual');
        $received = (int) $entries->filter(fn (CashEntry $entry) => $entry->direction === CashDirection::Nhan)->sum('amount_actual');

        return [
            'group' => $group,
            'paid' => $paid,
            'received' => $received,
            'profit' => HuiCalculator::profit($received, $paid),
            'projected' => $group->status === HuiGroupStatus::DangChay ? $this->projectedProfit($group) : null,
        ];
    }

    private function projectedProfit(HuiGroup $group): ?int
    {
        $bidsByRound = $group->rounds->pluck('bid_amount', 'round_no')->all();
        $winRoundBySlot = $group->rounds
            ->filter(fn (HuiRound $round): bool => $round->winner_type === WinnerType::Minh)
            ->mapWithKeys(fn (HuiRound $round): array => [(string) $round->winner_slot_id => $round->round_no])
            ->all();
        $takenRoundNos = $group->rounds
            ->filter(fn (HuiRound $round): bool => $round->winner_type !== WinnerType::ChuaCo)
            ->pluck('round_no')
            ->all();
        $unwonSlots = $group->slots->filter(fn (MySlot $slot): bool => ! isset($winRoundBySlot[$slot->id]))->values();

        try {
            $assumedRounds = HuiCalculator::assumedWinRounds($group->total_slots, $takenRoundNos, $unwonSlots->count());

            foreach ($unwonSlots as $index => $slot) {
                $winRoundBySlot[$slot->id] = $assumedRounds[$index];
            }

            return array_sum(array_map(
                fn (int $winRoundNo): int => HuiCalculator::projectedProfit($group->total_slots, $group->face_value, $group->commission_amount, $bidsByRound, $winRoundNo),
                $winRoundBySlot,
            ));
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
