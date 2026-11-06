<?php

declare(strict_types=1);

namespace App\Actions\Gold;

use App\Exceptions\HuiActionException;
use App\Models\GoldItem;
use App\Models\GoldSale;
use Illuminate\Support\Facades\DB;

/**
 * Thêm hoặc sửa một món vàng (document/06-vang.md VG-01, VG-02).
 */
final class SaveGoldItem
{
    /**
     * @param  array{gold_type: string, name?: string|null, weight_ly: int, cost_amount: int, bought_on: string, shop?: string|null, note?: string|null}  $attributes
     *
     * @throws HuiActionException khi món đã bán không còn khớp với dữ liệu mới.
     */
    public function handle(?GoldItem $item, array $attributes): GoldItem
    {
        if ($item === null) {
            return GoldItem::query()->create($attributes);
        }

        return DB::transaction(function () use ($item, $attributes): GoldItem {
            $locked = GoldItem::query()->lockForUpdate()->findOrFail($item->id);
            $sales = GoldSale::query()->where('gold_item_id', $locked->id);

            if ($attributes['weight_ly'] < (int) $sales->sum('weight_ly')) {
                throw new HuiActionException('Số chỉ không được nhỏ hơn số đã bán.');
            }

            $firstSoldOn = $sales->min('sold_on');

            if ($firstSoldOn !== null && $attributes['bought_on'] > substr((string) $firstSoldOn, 0, 10)) {
                throw new HuiActionException('Ngày mua không được sau lần bán đầu tiên.');
            }

            $locked->fill($attributes)->save();

            return $locked;
        });
    }
}
