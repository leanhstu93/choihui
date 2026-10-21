<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CashEntry;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
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
        ];
    }
}
