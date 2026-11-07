<?php

declare(strict_types=1);

namespace App\Actions\Repairs;

use App\Models\RepairRecord;
use App\Models\RepairRecordPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Xóa một lần sửa cùng ảnh của nó.
 */
final class DeleteRepairRecord
{
    public function handle(RepairRecord $record): void
    {
        $paths = DB::transaction(function () use ($record): array {
            $paths = $record->photos()->pluck('path')->all();
            $record->delete();

            return $paths;
        });

        Storage::disk(RepairRecordPhoto::DISK)->delete($paths);
    }
}
