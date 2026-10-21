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

/**
 * Tổng thu / chi / nợ / lời lỗ của một dây chủ hụi (document/04-chu-hui.md mục 5).
 */
final class HostReport
{
    /**
     * @return array{collected: int, paidOut: int, debt: int, writtenOff: int, profit: int, projected: int|null}
     */
    public function forGroup(HuiGroup $group): array
    {
        $group->loadMissing(['rounds.hostEntries']);
        $entries = $group->rounds->flatMap(fn (HuiRound $round) => $round->hostEntries);
        $collections = $entries->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Thu);
        $writtenOff = $collections->filter(fn (HostEntry $entry): bool => $entry->status === CashEntryStatus::Bung);

        $collected = (int) $collections->sum('amount_actual');
        $paidOut = (int) $entries->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Chi)->sum('amount_actual');
        $debt = HostLedger::debt($collections->diff($writtenOff)
            ->map(fn (HostEntry $entry): array => ['expected' => $entry->amount_expected, 'actual' => $entry->amount_actual])
            ->values()
            ->all());
        $profit = HostLedger::hostProfit(
            $collected,
            $paidOut,
            $debt,
            $group->commission_amount,
            $group->rounds->filter(fn (HuiRound $round): bool => $round->winner_host_slot_id === null)->count(),
        );

        return [
            'collected' => $collected,
            'paidOut' => $paidOut,
            'debt' => $debt,
            'writtenOff' => HostLedger::debt($writtenOff
                ->map(fn (HostEntry $entry): array => ['expected' => $entry->amount_expected, 'actual' => $entry->amount_actual])
                ->values()
                ->all()),
            'profit' => $profit['actual'],
            'projected' => $group->status === HuiGroupStatus::DangChay ? $profit['projected'] : null,
        ];
    }
}
