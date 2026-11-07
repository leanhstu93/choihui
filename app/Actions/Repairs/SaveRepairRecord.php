<?php

declare(strict_types=1);

namespace App\Actions\Repairs;

use App\Models\RepairItem;
use App\Models\RepairRecord;

/**
 * Thêm hoặc sửa một lần sửa (document/08-sua-chua.md SC-03).
 */
final class SaveRepairRecord
{
    /**
     * @param  array{repaired_on: string, part: string, amount: int, place?: string|null, warranty_until?: string|null, odometer_km?: int|null, note?: string|null}  $attributes
     */
    public function handle(RepairItem $item, ?RepairRecord $record, array $attributes): RepairRecord
    {
        if ($record === null) {
            return $item->records()->create($attributes);
        }

        $record->fill($attributes)->save();

        return $record;
    }
}
