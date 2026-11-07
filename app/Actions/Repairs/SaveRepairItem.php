<?php

declare(strict_types=1);

namespace App\Actions\Repairs;

use App\Models\RepairItem;

/**
 * Thêm hoặc sửa một món đồ (document/08-sua-chua.md SC-01).
 */
final class SaveRepairItem
{
    /**
     * @param  array{name: string, tracks_km: bool, note?: string|null}  $attributes
     */
    public function handle(?RepairItem $item, array $attributes): RepairItem
    {
        if ($item === null) {
            return RepairItem::query()->create($attributes);
        }

        $item->fill($attributes)->save();

        return $item;
    }
}
