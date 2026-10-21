<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Models\CashEntry;
use App\Models\HostEntry;
use App\Models\HostSlot;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use Illuminate\Support\Facades\DB;

/**
 * Xóa dây và toàn bộ dữ liệu của nó theo thứ tự rõ ràng trong một transaction
 * (document/01-dac-ta.md mục 8), không phụ thuộc cascade của database.
 */
final class DeleteGroup
{
    public function handle(HuiGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            CashEntry::query()->whereIn('round_id', HuiRound::query()->where('group_id', $group->id)->select('id'))->delete();
            HostEntry::query()->whereIn('round_id', HuiRound::query()->where('group_id', $group->id)->select('id'))->delete();
            HuiRound::query()->where('group_id', $group->id)->delete();
            MySlot::query()->where('group_id', $group->id)->delete();
            HostSlot::query()->where('group_id', $group->id)->delete();
            $group->delete();
        });
    }
}
