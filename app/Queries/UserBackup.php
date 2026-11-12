<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CashEntry;
use App\Models\FamilyEvent;
use App\Models\GoldItem;
use App\Models\GoldPrice;
use App\Models\GoldSale;
use App\Models\HealthPerson;
use App\Models\HealthRecord;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use App\Models\NetWorthSnapshot;
use App\Models\RepairItem;
use App\Models\RepairRecord;
use App\Models\User;

/**
 * Bản sao lưu toàn bộ dữ liệu của một người dùng ra JSON (FR-11). Khôi phục bằng
 * Actions\Backup\RestoreBackup. Lọc user_id tường minh, không phụ thuộc người đăng nhập.
 *
 * Tay được tham chiếu bằng "key" (id gốc) chỉ trong phạm vi file; khi khôi phục sinh id mới.
 */
final class UserBackup
{
    public const APP = 'so-hui';

    public const VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $groups = HuiGroup::query()
            ->withoutGlobalScope(HuiGroup::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->with([
                'slots' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE)->orderBy('label'),
                'rounds' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
                'rounds.cashEntries' => fn ($query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE),
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return [
            'app' => self::APP,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'settings' => ['reminder_days_before' => $user->reminder_days_before],
            'groups' => $groups->map(fn (HuiGroup $group) => [
                'name' => $group->name,
                'type' => $group->type->value,
                'frequency' => $group->frequency->value,
                'face_value' => $group->face_value,
                'total_slots' => $group->total_slots,
                'start_date' => $group->start_date->toDateString(),
                'due_offset_days' => $group->due_offset_days,
                'host_name' => $group->host_name,
                'host_phone' => $group->host_phone,
                'commission_amount' => $group->commission_amount,
                'status' => $group->status->value,
                'broken_round_no' => $group->broken_round_no,
                'note' => $group->note,
                'slots' => $group->slots->map(fn (MySlot $slot) => ['key' => $slot->id, 'label' => $slot->label])->all(),
                'rounds' => $group->rounds->map(fn (HuiRound $round) => [
                    'round_no' => $round->round_no,
                    'open_date' => $round->open_date->toDateString(),
                    'bid_amount' => $round->bid_amount,
                    'winner_type' => $round->winner_type->value,
                    'winner_slot' => $round->winner_slot_id,
                    'winner_name' => $round->winner_name,
                    'entries' => $round->cashEntries->map(fn (CashEntry $entry) => [
                        'slot' => $entry->slot_id,
                        'direction' => $entry->direction->value,
                        'amount_expected' => $entry->amount_expected,
                        'amount_actual' => $entry->amount_actual,
                        'paid_date' => $entry->paid_date?->toDateString(),
                        'status' => $entry->status->value,
                        'note' => $entry->note,
                    ])->values()->all(),
                ])->all(),
            ])->all(),
            'gold' => $this->exportGold($user),
            'net_worth' => NetWorthSnapshot::query()
                ->withoutGlobalScope(NetWorthSnapshot::OWNER_SCOPE)
                ->where('user_id', $user->id)
                ->orderBy('month')
                ->get()
                ->map(fn (NetWorthSnapshot $snapshot) => $snapshot->only(['month', 'assets', 'debts']))
                ->all(),
            'repairs' => $this->exportRepairs($user),
            'family' => $this->exportFamily($user),
        ];
    }

    /**
     * Sổ vàng (document/06-vang.md V11).
     *
     * @return array{items: list<array<string, mixed>>, prices: list<array<string, mixed>>}
     */
    private function exportGold(User $user): array
    {
        $items = GoldItem::query()
            ->withoutGlobalScope(GoldItem::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->with(['sales' => fn ($query) => $query->withoutGlobalScope(GoldSale::OWNER_SCOPE)])
            ->orderBy('bought_on')
            ->orderBy('id')
            ->get();
        $prices = GoldPrice::query()
            ->withoutGlobalScope(GoldPrice::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->orderBy('gold_type')
            ->get();

        return [
            'items' => $items->map(fn (GoldItem $item) => [
                'gold_type' => $item->gold_type->value,
                'name' => $item->name,
                'weight_ly' => $item->weight_ly,
                'cost_amount' => $item->cost_amount,
                'bought_on' => $item->bought_on->toDateString(),
                'shop' => $item->shop,
                'note' => $item->note,
                'sales' => $item->sales->map(fn (GoldSale $sale) => [
                    'weight_ly' => $sale->weight_ly,
                    'amount' => $sale->amount,
                    'sold_on' => $sale->sold_on->toDateString(),
                    'note' => $sale->note,
                ])->values()->all(),
            ])->values()->all(),
            'prices' => $prices->map(fn (GoldPrice $price) => [
                'gold_type' => $price->gold_type->value,
                'price_per_chi' => $price->price_per_chi,
                'priced_on' => $price->priced_on->toDateString(),
            ])->values()->all(),
        ];
    }

    /**
     * Sổ sửa chữa (document/08-sua-chua.md S7).
     *
     * @return array{items: list<array<string, mixed>>}
     */
    private function exportRepairs(User $user): array
    {
        $items = RepairItem::query()
            ->withoutGlobalScope(RepairItem::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->with(['records' => fn ($query) => $query->withoutGlobalScope(RepairRecord::OWNER_SCOPE)->orderBy('repaired_on')->orderBy('id')])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return [
            'items' => $items->map(fn (RepairItem $item) => [
                'name' => $item->name,
                'tracks_km' => $item->tracks_km,
                'note' => $item->note,
                'records' => $item->records->map(fn (RepairRecord $record) => [
                    'repaired_on' => $record->repaired_on->toDateString(),
                    'part' => $record->part,
                    'amount' => $record->amount,
                    'place' => $record->place,
                    'warranty_until' => $record->warranty_until?->toDateString(),
                    'odometer_km' => $record->odometer_km,
                    'note' => $record->note,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * Sổ gia đình (document/09-gia-dinh.md G12). Ảnh không nằm trong file.
     *
     * @return array{events: list<array<string, mixed>>, people: list<array<string, mixed>>}
     */
    private function exportFamily(User $user): array
    {
        $events = FamilyEvent::query()
            ->withoutGlobalScope(FamilyEvent::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
        $people = HealthPerson::query()
            ->withoutGlobalScope(HealthPerson::OWNER_SCOPE)
            ->where('user_id', $user->id)
            ->with(['records' => fn ($query) => $query->withoutGlobalScope(HealthRecord::OWNER_SCOPE)->orderBy('visited_on')->orderBy('id')])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return [
            'events' => $events->map(fn (FamilyEvent $event) => [
                'title' => $event->title,
                'kind' => $event->kind->value,
                'calendar' => $event->calendar->value,
                'day' => $event->day,
                'month' => $event->month,
                'origin_year' => $event->origin_year,
                'remind_days_before' => $event->remind_days_before,
                'note' => $event->note,
            ])->values()->all(),
            'people' => $people->map(fn (HealthPerson $person) => [
                'name' => $person->name,
                'birth_year' => $person->birth_year,
                'note' => $person->note,
                'records' => $person->records->map(fn (HealthRecord $record) => [
                    'visited_on' => $record->visited_on->toDateString(),
                    'kind' => $record->kind->value,
                    'title' => $record->title,
                    'place' => $record->place,
                    'amount' => $record->amount,
                    'follow_up_on' => $record->follow_up_on?->toDateString(),
                    'note' => $record->note,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
