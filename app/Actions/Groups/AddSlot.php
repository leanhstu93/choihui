<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Actions\Rounds\SyncCashEntries;
use App\Exceptions\HuiActionException;
use App\Models\HuiGroup;
use App\Models\MySlot;
use Illuminate\Support\Facades\DB;

/**
 * Thêm một tay của mình vào dây (FR-02); tay mới có khoản phải đóng ở các kỳ đã có kết quả.
 */
final class AddSlot
{
    public function __construct(private SyncCashEntries $syncCashEntries) {}

    public function handle(HuiGroup $group, string $label): MySlot
    {
        if ($group->slots()->count() >= $group->total_slots) {
            throw new HuiActionException('Số tay của mình không được vượt quá tổng số tay của dây.');
        }

        if ($group->slots()->where('label', $label)->exists()) {
            throw new HuiActionException('Tên tay đã có trong dây này.');
        }

        return DB::transaction(function () use ($group, $label): MySlot {
            $slot = $group->slots()->create(['label' => $label]);
            $this->syncCashEntries->handle($group);

            return $slot;
        });
    }
}
