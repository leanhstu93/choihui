<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\HostLedger;
use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Enums\HuiGroupStatus;
use App\Models\HostEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Tổng thu / chi / thảo / bù / nợ / lời lỗ của dây chủ hụi (document/04-chu-hui.md mục 5, CH-12).
 *
 * Lọc user_id tường minh ở forUser, như các Query khác.
 */
final class HostReport
{
    /**
     * Báo cáo chủ hụi từng dây và tổng (CH-12).
     *
     * @return array{groups: list<array{group: HuiGroup, collected: int, paidOut: int, commissionReceived: int, commissionProjected: int, topUp: int, debt: int, writtenOff: int, profit: int, projected: int|null}>, totals: array{collected: int, paidOut: int, commissionReceived: int, commissionProjected: int, topUp: int, debt: int, writtenOff: int, profit: int, projected: int}}
     */
    public function forUser(User $user): array
    {
        $groups = HuiGroup::query()
            ->withoutGlobalScope(HuiGroup::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->with([
                'rounds' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds.hostEntries' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
            ])
            ->orderByRaw("status = 'dang_chay' desc")
            ->latest()
            ->get();

        $rows = [];
        $totals = array_fill_keys(['collected', 'paidOut', 'commissionReceived', 'commissionProjected', 'topUp', 'debt', 'writtenOff', 'profit', 'projected'], 0);

        foreach ($groups as $group) {
            $row = ['group' => $group] + $this->forGroup($group);
            $rows[] = $row;

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $key === 'projected' ? ($row['projected'] ?? $row['profit']) : $row[$key];
            }
        }

        return ['groups' => $rows, 'totals' => $totals];
    }

    /**
     * Thảo đã nhận = thảo × số kỳ đã chi cho người hốt. Mình bù = cộng phần chi
     * vượt số đã thu của từng kỳ.
     *
     * @return array{collected: int, paidOut: int, commissionReceived: int, commissionProjected: int, topUp: int, debt: int, writtenOff: int, profit: int, projected: int|null}
     */
    public function forGroup(HuiGroup $group): array
    {
        $group->loadMissing(['rounds.hostEntries']);
        $entries = $group->rounds->flatMap(fn (HuiRound $round) => $round->hostEntries);
        $collections = $entries->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Thu);
        $writtenOff = $collections->filter(fn (HostEntry $entry): bool => $entry->status === CashEntryStatus::Bung);

        $collected = (int) $collections->sum('amount_actual');
        $paidOut = (int) $entries->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Chi)->sum('amount_actual');
        $debt = HostLedger::debt($this->shortfalls($collections->diff($writtenOff)));
        $isRunning = $group->status === HuiGroupStatus::DangChay;
        $profit = HostLedger::hostProfit(
            $collected,
            $paidOut,
            $debt,
            $group->commission_amount,
            $group->rounds->filter(fn (HuiRound $round): bool => $round->winner_host_slot_id === null)->count(),
        );
        $paidOutRounds = $group->rounds->filter(fn (HuiRound $round): bool => $round->hostEntries->contains(
            fn (HostEntry $entry): bool => $entry->direction === HostDirection::Chi && $entry->amount_actual > 0,
        ));

        return [
            'collected' => $collected,
            'paidOut' => $paidOut,
            'commissionReceived' => $group->commission_amount * $paidOutRounds->count(),
            'commissionProjected' => $group->commission_amount * ($isRunning
                ? $group->total_slots
                : $group->rounds->filter(fn (HuiRound $round): bool => $round->winner_host_slot_id !== null)->count()),
            'topUp' => (int) $group->rounds->sum(fn (HuiRound $round): int => HostLedger::topUp(
                (int) $round->hostEntries->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Thu)->sum('amount_actual'),
                (int) $round->hostEntries->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Chi)->sum('amount_actual'),
            )),
            'debt' => $debt,
            'writtenOff' => HostLedger::debt($this->shortfalls($writtenOff)),
            'profit' => $profit['actual'],
            'projected' => $isRunning ? $profit['projected'] : null,
        ];
    }

    /**
     * @param  Collection<int, HostEntry>  $entries
     * @return list<array{expected: int, actual: int}>
     */
    private function shortfalls(Collection $entries): array
    {
        return $entries
            ->map(fn (HostEntry $entry): array => ['expected' => $entry->amount_expected, 'actual' => $entry->amount_actual])
            ->values()
            ->all();
    }
}
