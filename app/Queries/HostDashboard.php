<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\HuiSchedule;
use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Enums\HuiGroupStatus;
use App\Models\HostEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\User;
use DateTimeImmutable;

/**
 * Trang chủ chủ hụi (CH-08): các kỳ còn khoản chưa thu / chưa chi, và các kỳ đã
 * hoặc sắp mở mà chưa nhập kết quả, của mọi dây đang chạy.
 *
 * Lọc user_id tường minh thay vì dựa vào global scope, để dùng được cả khi chạy nền
 * (email nhắc chủ hụi, CH-13).
 */
final class HostDashboard
{
    /**
     * @return array{
     *     rounds: list<array{round: HuiRound, group: HuiGroup, dueDate: DateTimeImmutable, isOverdue: bool, expected: int, collected: int, unpaid: list<HostEntry>, payout: HostEntry|null}>,
     *     pendingResults: list<array{round: HuiRound, group: HuiGroup}>,
     *     totals: array{toCollect: int, toPayOut: int, overdueCount: int}
     * }
     */
    public function forUser(User $user, DateTimeImmutable $today, DateTimeImmutable $until): array
    {
        $groups = HuiGroup::query()
            ->withoutGlobalScope(HuiGroup::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->where('status', HuiGroupStatus::DangChay)
            ->with([
                'rounds' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds.hostEntries' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds.hostEntries.hostSlot' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds.hostEntries.hostSlot.member' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
            ])
            ->get();

        $rounds = [];
        $pendingResults = [];

        foreach ($groups as $group) {
            foreach ($group->rounds as $round) {
                if ($round->winner_host_slot_id === null) {
                    if ($round->open_date->toDateString() <= $until->format('Y-m-d')) {
                        $pendingResults[] = ['round' => $round, 'group' => $group];
                    }

                    continue;
                }

                $row = $this->openRound($group, $round, $today);

                if ($row !== null) {
                    $rounds[] = $row;
                }
            }
        }

        usort($rounds, fn (array $a, array $b): int => [$a['dueDate'], $a['group']->name] <=> [$b['dueDate'], $b['group']->name]);
        usort($pendingResults, fn (array $a, array $b): int => [$a['round']->open_date, $a['group']->name] <=> [$b['round']->open_date, $b['group']->name]);

        return [
            'rounds' => $rounds,
            'pendingResults' => $pendingResults,
            'totals' => [
                'toCollect' => array_sum(array_map(
                    fn (array $row): int => array_sum(array_map(fn (HostEntry $entry): int => $entry->amount_expected - $entry->amount_actual, $row['unpaid'])),
                    $rounds,
                )),
                'toPayOut' => array_sum(array_map(fn (array $row): int => $row['payout'] === null ? 0 : $row['payout']->amount_expected, $rounds)),
                'overdueCount' => count(array_filter($rounds, fn (array $row): bool => $row['isOverdue'] && $row['unpaid'] !== [])),
            ],
        ];
    }

    /**
     * Kỳ còn khoản thu chưa đủ hoặc khoản chi chưa chi; null nếu kỳ đã xong.
     *
     * @return array{round: HuiRound, group: HuiGroup, dueDate: DateTimeImmutable, isOverdue: bool, expected: int, collected: int, unpaid: list<HostEntry>, payout: HostEntry|null}|null
     */
    private function openRound(HuiGroup $group, HuiRound $round, DateTimeImmutable $today): ?array
    {
        $collections = $round->hostEntries
            ->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Thu)
            ->sortBy('hostSlot.slot_no');
        $unpaid = $collections
            ->filter(fn (HostEntry $entry): bool => in_array($entry->status, [CashEntryStatus::Chua, CashEntryStatus::Thieu], true))
            ->values()
            ->all();
        $payout = $round->hostEntries->first(
            fn (HostEntry $entry): bool => $entry->direction === HostDirection::Chi && $entry->status === CashEntryStatus::Chua,
        );

        if ($unpaid === [] && $payout === null) {
            return null;
        }

        $dueDate = HuiSchedule::dueDate($round->open_date->toDateTimeImmutable(), $group->due_offset_days);

        return [
            'round' => $round,
            'group' => $group,
            'dueDate' => $dueDate,
            'isOverdue' => $dueDate->format('Y-m-d') < $today->format('Y-m-d'),
            'expected' => (int) $collections->sum('amount_expected'),
            'collected' => (int) $collections->sum('amount_actual'),
            'unpaid' => $unpaid,
            'payout' => $payout,
        ];
    }
}
