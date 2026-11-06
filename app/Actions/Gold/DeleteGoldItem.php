<?php

declare(strict_types=1);

namespace App\Actions\Gold;

use App\Exceptions\HuiActionException;
use App\Models\GoldItem;
use App\Models\GoldSale;
use Illuminate\Support\Facades\DB;

/**
 * Xóa món vàng chưa có giao dịch bán (document/06-vang.md V10).
 */
final class DeleteGoldItem
{
    public function handle(GoldItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $locked = GoldItem::query()->lockForUpdate()->findOrFail($item->id);

            if (GoldSale::query()->where('gold_item_id', $locked->id)->exists()) {
                throw new HuiActionException('Món này đã có giao dịch bán, hãy xóa giao dịch bán trước.');
            }

            $locked->delete();
        });
    }
}
