<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Host\SyncHostEntries;
use App\Actions\Rounds\SyncCashEntries;
use App\Enums\HuiGroupStatus;
use App\Exceptions\HuiActionException;
use App\Models\HuiGroup;
use Illuminate\Support\Facades\DB;

/**
 * Bỏ đánh dấu bể (nhập nhầm): dây chạy lại và các khoản phải đóng được sinh lại.
 */
final class UndoGroupBroken
{
    public function __construct(private SyncCashEntries $syncCashEntries, private SyncHostEntries $syncHostEntries) {}

    public function handle(HuiGroup $group): HuiGroup
    {
        if ($group->status !== HuiGroupStatus::Be) {
            throw new HuiActionException('Dây này không ở trạng thái bể.');
        }

        return DB::transaction(function () use ($group): HuiGroup {
            $group->update(['status' => HuiGroupStatus::DangChay, 'broken_round_no' => null]);
            $this->syncCashEntries->handle($group);
            $this->syncHostEntries->handle($group);

            return $group;
        });
    }
}
