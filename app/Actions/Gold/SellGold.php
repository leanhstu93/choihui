<?php

declare(strict_types=1);

namespace App\Actions\Gold;

use App\Exceptions\HuiActionException;
use App\Models\GoldItem;
use App\Models\GoldSale;
use Illuminate\Support\Facades\DB;

/**
 * Bán toàn bộ hoặc một phần món vàng (document/06-vang.md VG-04). Khóa dòng món vàng
 * để hai lần bấm cùng lúc không bán quá phần còn lại.
 */
final class SellGold
{
    /**
     * @param  array{weight_ly: int, amount: int, sold_on: string, note?: string|null}  $attributes
     *
     * @throws HuiActionException
     */
    public function handle(GoldItem $item, array $attributes): GoldSale
    {
        return DB::transaction(function () use ($item, $attributes): GoldSale {
            $locked = GoldItem::query()->lockForUpdate()->findOrFail($item->id);
            $sold = (int) GoldSale::query()->where('gold_item_id', $locked->id)->sum('weight_ly');

            if ($attributes['weight_ly'] > $locked->weight_ly - $sold) {
                throw new HuiActionException('Số chỉ bán vượt quá phần còn lại của món này.');
            }

            if ($attributes['sold_on'] < $locked->bought_on->toDateString()) {
                throw new HuiActionException('Ngày bán không được trước ngày mua.');
            }

            /** @var GoldSale */
            return $locked->sales()->create($attributes);
        });
    }
}
