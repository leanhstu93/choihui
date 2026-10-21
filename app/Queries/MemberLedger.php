<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\HostLedger;
use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Models\HostEntry;
use App\Models\HostSlot;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * Số đã đóng và nợ của hụi viên trên các tay họ giữ (CH-09, document/04-chu-hui.md mục 5).
 * Nợ = phần còn thiếu của các khoản thu, không tính khoản đã đánh dấu bùng.
 */
final class MemberLedger
{
    /**
     * @return array{slots: list<array{slot: HostSlot, paid: int, debt: int}>, paid: int, debt: int}
     */
    public function forMember(Member $member): array
    {
        $member->loadMissing(['hostSlots.group', 'hostSlots.wonRound', 'hostSlots.hostEntries']);

        $slots = $member->hostSlots
            ->sortBy([['group.created_at', 'desc'], ['slot_no', 'asc']])
            ->map(fn (HostSlot $slot): array => [
                'slot' => $slot,
                'paid' => $this->collections($slot->hostEntries)->sum('amount_actual'),
                'debt' => $this->debtOf($slot->hostEntries),
            ])
            ->values()
            ->all();

        return [
            'slots' => $slots,
            'paid' => array_sum(array_column($slots, 'paid')),
            'debt' => array_sum(array_column($slots, 'debt')),
        ];
    }

    /**
     * Nợ của nhiều hụi viên cùng lúc, cho danh bạ.
     *
     * @param  Collection<int, Member>  $members
     * @return array<string, int> member_id => nợ
     */
    public function debtByMember(Collection $members): array
    {
        return $members
            ->mapWithKeys(fn (Member $member): array => [
                $member->id => $this->debtOf($member->hostSlots->flatMap(fn (HostSlot $slot) => $slot->hostEntries)),
            ])
            ->all();
    }

    /**
     * Nợ trên một nhóm khoản (ví dụ các khoản của một tay).
     *
     * @param  Collection<int, HostEntry>  $entries
     */
    public function debtOf(Collection $entries): int
    {
        return HostLedger::debt($this->collections($entries)
            ->reject(fn (HostEntry $entry): bool => $entry->status === CashEntryStatus::Bung)
            ->map(fn (HostEntry $entry): array => ['expected' => $entry->amount_expected, 'actual' => $entry->amount_actual])
            ->values()
            ->all());
    }

    /**
     * @param  Collection<int, HostEntry>  $entries
     * @return Collection<int, HostEntry>
     */
    private function collections(Collection $entries): Collection
    {
        return $entries->filter(fn (HostEntry $entry): bool => $entry->direction === HostDirection::Thu);
    }
}
