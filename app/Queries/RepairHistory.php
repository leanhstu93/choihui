<?php

declare(strict_types=1);

namespace App\Queries;

use App\Domain\RepairLedger;
use App\Models\RepairItem;
use App\Models\RepairRecord;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Dữ liệu các màn sổ sửa chữa (document/08-sua-chua.md). Lọc user_id tường minh.
 */
final class RepairHistory
{
    /**
     * Mọi món đồ, món vừa sửa gần đây lên đầu, món chưa có lần sửa xuống cuối.
     *
     * @return array{items: Collection<int, RepairItem>, total: int, yearTotal: int, year: int}
     */
    public function forUser(User $user): array
    {
        $today = now()->toImmutable();
        $records = RepairRecord::query()->where('user_id', $user->id);

        return [
            'items' => RepairItem::query()
                ->where('user_id', $user->id)
                ->withCount('records')
                ->withSum('records', 'amount')
                ->withMax('records', 'repaired_on')
                ->orderByDesc('records_max_repaired_on')
                ->orderByDesc('id')
                ->get(),
            'total' => (int) (clone $records)->sum('amount'),
            'yearTotal' => (int) (clone $records)
                ->whereBetween('repaired_on', [$today->startOfYear()->toDateString(), $today->endOfYear()->toDateString()])
                ->sum('amount'),
            'year' => $today->year,
        ];
    }

    /**
     * Lịch sử một món, lần mới nhất lên đầu. Các dòng cùng hạng mục được ghép theo thứ tự
     * ngày để tính độ bền.
     *
     * @return array{entries: list<RepairEntry>, parts: list<RepairPartSummary>, total: int}
     */
    public function forItem(RepairItem $item): array
    {
        $today = now()->toImmutable()->startOfDay();
        $records = $this->recordsOf($item);

        $entries = [];
        /** @var array<string, RepairRecord> $latest */
        $latest = [];
        /** @var array<string, array{count: int, total: int}> $tally */
        $tally = [];

        foreach ($records as $record) {
            $key = RepairLedger::partKey($record->part);
            $previous = $latest[$key] ?? null;

            $entries[] = new RepairEntry(
                $record,
                $previous === null ? null : RepairLedger::duration($previous->repaired_on, $record->repaired_on),
                RepairLedger::distance($previous?->odometer_km, $record->odometer_km),
            );

            $latest[$key] = $record;
            $tally[$key] = [
                'count' => ($tally[$key]['count'] ?? 0) + 1,
                'total' => ($tally[$key]['total'] ?? 0) + $record->amount,
            ];
        }

        $parts = [];

        foreach ($latest as $key => $record) {
            $parts[] = new RepairPartSummary(
                $record->part,
                $tally[$key]['count'],
                $tally[$key]['total'],
                RepairLedger::duration($record->repaired_on, $today),
            );
        }

        usort($parts, fn (RepairPartSummary $a, RepairPartSummary $b) => $b->total <=> $a->total);

        return [
            'entries' => array_reverse($entries),
            'parts' => $parts,
            'total' => (int) $records->sum('amount'),
        ];
    }

    /**
     * Tên hạng mục đã dùng trên món này, để gợi ý khi nhập lần sau.
     *
     * @return list<string>
     */
    public function partNames(RepairItem $item): array
    {
        $names = [];

        foreach ($this->recordsOf($item) as $record) {
            $names[RepairLedger::partKey($record->part)] = $record->part;
        }

        return array_values($names);
    }

    /**
     * @return Collection<int, RepairRecord>
     */
    private function recordsOf(RepairItem $item): Collection
    {
        return RepairRecord::query()
            ->where('user_id', $item->user_id)
            ->where('repair_item_id', $item->id)
            ->with(['photos' => fn ($query) => $query->oldest('id')])
            ->orderBy('repaired_on')
            ->orderBy('id')
            ->get()
            ->toBase();
    }
}
