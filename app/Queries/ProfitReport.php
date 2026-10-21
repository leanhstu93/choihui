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
 * Mỗi dây có một con số lời/lỗ chính (outlook), giống nhau ở mọi màn hình:
 * - Đang chạy, còn tay chưa hốt: "Nếu hốt kỳ k", tay chưa hốt giả định hốt các
 *   kỳ còn trống sớm nhất.
 * - Đang chạy, đã hốt hết: "Dự kiến mãn dây", tính cả các kỳ còn phải đóng chết.
 * - Bể / kết thúc: thực tế = tiền đã nhận − tiền đã đóng.
 * Kèm ifWinLast khi còn tay chưa hốt: giả định hốt các kỳ cuối còn trống.
 * Kỳ chưa mở lấy lãi thăm của kỳ gần nhất đã mở.
 */
final class ProfitReport
{
    /**
     * @return array{groups: list<array{group: HuiGroup, paid: int, received: int, profit: int, outlook: array{label: string, profit: int}, ifWinLast: int|null}>, totals: array{paid: int, received: int, outlook: int, ifWinLast: int}}
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
        $totals = ['paid' => 0, 'received' => 0, 'outlook' => 0, 'ifWinLast' => 0];

        foreach ($groups as $group) {
            $row = $this->forGroup($group);
            $rows[] = $row;
            $totals['paid'] += $row['paid'];
            $totals['received'] += $row['received'];
            $totals['outlook'] += $row['outlook']['profit'];
            $totals['ifWinLast'] += $row['ifWinLast'] ?? $row['outlook']['profit'];
        }

        return ['groups' => $rows, 'totals' => $totals];
    }

    /**
     * Lời/lỗ của một dây. Dây phải có sẵn slots, rounds.cashEntries (nạp nếu thiếu).
     *
     * @return array{group: HuiGroup, paid: int, received: int, profit: int, outlook: array{label: string, profit: int}, ifWinLast: int|null}
     */
    public function forGroup(HuiGroup $group): array
    {
        $group->loadMissing(['slots', 'rounds.cashEntries']);

        $entries = $group->rounds->flatMap(fn (HuiRound $round) => $round->cashEntries);
        $paid = (int) $entries->filter(fn (CashEntry $entry) => $entry->direction === CashDirection::Dong)->sum('amount_actual');
        $received = (int) $entries->filter(fn (CashEntry $entry) => $entry->direction === CashDirection::Nhan)->sum('amount_actual');
        $profit = HuiCalculator::profit($received, $paid);

        $ifWinNext = null;
        $ifWinLast = null;

        if ($group->status === HuiGroupStatus::DangChay) {
            $ifWinNext = $this->projectedProfit($group, winEarliest: true);
            $ifWinLast = $this->projectedProfit($group, winEarliest: false)['profit'] ?? null;
        }

        $outlook = match (true) {
            $ifWinNext !== null => ['label' => 'Nếu hốt kỳ '.$ifWinNext['round_no'], 'profit' => $ifWinNext['profit']],
            $ifWinLast !== null => ['label' => 'Dự kiến mãn dây', 'profit' => $ifWinLast],
            default => ['label' => 'Lời / lỗ', 'profit' => $profit],
        };

        return [
            'group' => $group,
            'paid' => $paid,
            'received' => $received,
            'profit' => $profit,
            'outlook' => $outlook,
            'ifWinLast' => $ifWinNext !== null ? $ifWinLast : null,
        ];
    }

    /**
     * Tổng lời/lỗ dự kiến của các tay; round_no là kỳ giả định của tay chưa hốt
     * đầu tiên. Null khi dữ liệu không đủ để tính, hoặc (khi winEarliest) khi
     * không còn tay chưa hốt.
     *
     * @return array{round_no: int, profit: int}|null
     */
    private function projectedProfit(HuiGroup $group, bool $winEarliest): ?array
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
            $assumedRounds = $winEarliest
                ? HuiCalculator::nextWinRounds($group->total_slots, $takenRoundNos, $unwonSlots->count())
                : HuiCalculator::assumedWinRounds($group->total_slots, $takenRoundNos, $unwonSlots->count());

            if ($winEarliest && $assumedRounds === []) {
                return null;
            }

            foreach ($unwonSlots as $index => $slot) {
                $winRoundBySlot[$slot->id] = $assumedRounds[$index];
            }

            return [
                'round_no' => $assumedRounds[0] ?? 0,
                'profit' => array_sum(array_map(
                    fn (int $winRoundNo): int => HuiCalculator::projectedProfit($group->total_slots, $group->face_value, $group->commission_amount, $bidsByRound, $winRoundNo),
                    $winRoundBySlot,
                )),
            ];
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
