<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Exceptions\HuiActionException;
use App\Models\MySlot;
use Illuminate\Support\Facades\DB;

/**
 * Xóa một tay khai báo nhầm. Không xóa tay đã hốt hoặc đã ghi nhận tiền để không mất lịch sử.
 */
final class RemoveSlot
{
    public function handle(MySlot $slot): void
    {
        if ($slot->hasWon()) {
            throw new HuiActionException('Tay này đã hốt nên không thể xóa.');
        }

        if ($slot->cashEntries()->where('amount_actual', '>', 0)->exists()) {
            throw new HuiActionException('Tay này đã có khoản đóng tiền nên không thể xóa.');
        }

        DB::transaction(function () use ($slot): void {
            $slot->cashEntries()->delete();
            $slot->delete();
        });
    }
}
