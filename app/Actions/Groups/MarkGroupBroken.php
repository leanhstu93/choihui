<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Rounds\SyncCashEntries;
use App\Enums\HuiGroupStatus;
use App\Exceptions\HuiActionException;
use App\Models\HuiGroup;
use Illuminate\Support\Facades\DB;

/**
 * Đánh dấu dây bể từ kỳ b (FR-17): từ kỳ b không còn khoản phải đóng, các khoản đã
 * đóng giữ nguyên làm lịch sử.
 */
final class MarkGroupBroken
{
    public function __construct(private SyncCashEntries $syncCashEntries) {}

    public function handle(HuiGroup $group, int $brokenRoundNo): HuiGroup
    {
        if ($brokenRoundNo < 1 || $brokenRoundNo > $group->total_slots) {
            throw new HuiActionException("Kỳ bể phải từ 1 đến {$group->total_slots}.");
        }

        return DB::transaction(function () use ($group, $brokenRoundNo): HuiGroup {
            $group->update(['status' => HuiGroupStatus::Be, 'broken_round_no' => $brokenRoundNo]);
            $this->syncCashEntries->handle($group);

            return $group;
        });
    }
}
