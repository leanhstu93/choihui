<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\HuiCalculator;
use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Enums\HuiGroupStatus;
use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\User;

/**
 * Tổng tài sản (document/07-tong-tai-san.md mục 3). Lọc user_id tường minh, không
 * phụ thuộc người đăng nhập.
 */
final class NetWorthReport
{
    public function __construct(private GoldPortfolio $goldPortfolio) {}

    /**
     * @return array{gold: int, goldUnpriced: int, huiPaid: int, huiDebt: int, groups: list<array{group: HuiGroup, paid: int, debt: int}>, assets: int, debts: int, net: int}
     */
    public function forUser(User $user): array
    {
        $goldTotals = $this->goldPortfolio->forUser($user)['totals'];
        $groups = $user->isHost() ? [] : $this->runningGroups($user);
        $huiPaid = array_sum(array_column($groups, 'paid'));
        $huiDebt = array_sum(array_column($groups, 'debt'));
        $assets = $goldTotals['value'] + $huiPaid;

        return [
            'gold' => $goldTotals['value'],
            'goldUnpriced' => $goldTotals['unpriced'],
            'huiPaid' => $huiPaid,
            'huiDebt' => $huiDebt,
            'groups' => $groups,
            'assets' => $assets,
            'debts' => $huiDebt,
            'net' => $assets - $huiDebt,
        ];
    }

    /**
     * @return list<array{group: HuiGroup, paid: int, debt: int}>
     */
    private function runningGroups(User $user): array
    {
        return HuiGroup::query()
            ->withoutGlobalScope(HuiGroup::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->where('status', HuiGroupStatus::DangChay)
            ->with([
                'slots' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds.cashEntries' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
            ])
            ->oldest()
            ->get()
            ->map(fn (HuiGroup $group): array => ['group' => $group] + $this->positionOf($group))
            ->values()
            ->all();
    }

    /**
     * @return array{paid: int, debt: int}
     */
    private function positionOf(HuiGroup $group): array
    {
        $paid = 0;
        $debt = 0;

        foreach ($group->slots as $slot) {
            $wonRound = $group->rounds->first(fn (HuiRound $round): bool => $round->winner_slot_id === $slot->id);
            $entries = $group->rounds
                ->flatMap(fn (HuiRound $round) => $round->cashEntries)
                ->filter(fn (CashEntry $entry): bool => $entry->slot_id === $slot->id && $entry->direction === CashDirection::Dong);

            if ($wonRound === null) {
                $paid += (int) $entries->sum('amount_actual');

                continue;
            }

            $debt += $this->debtOf($group, $slot, $wonRound, $entries->all());
        }

        return ['paid' => $paid, 'debt' => $debt];
    }

    /**
     * @param  array<int, CashEntry>  $entries  Các dòng đóng của tay này.
     */
    private function debtOf(HuiGroup $group, MySlot $slot, HuiRound $wonRound, array $entries): int
    {
        $unpaid = 0;

        foreach ($entries as $entry) {
            if ($entry->status !== CashEntryStatus::Bung) {
                $unpaid += max(0, $entry->amount_expected - $entry->amount_actual);
            }
        }

        $roundsWithoutEntry = $group->rounds
            ->filter(fn (HuiRound $round): bool => $round->round_no > $wonRound->round_no
                && $round->cashEntries->every(fn (CashEntry $entry): bool => $entry->slot_id !== $slot->id))
            ->count();

        return HuiCalculator::remainingDebt($unpaid, $roundsWithoutEntry, $group->face_value);
    }
}
