<?php

declare(strict_types=1);

namespace App\Actions\Health;

use App\Models\HealthRecord;
use App\Models\HealthRecordPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Xóa một lần khám cùng ảnh của nó.
 */
final class DeleteHealthRecord
{
    public function handle(HealthRecord $record): void
    {
        $paths = DB::transaction(function () use ($record): array {
            $paths = $record->photos()->pluck('path')->all();
            $record->delete();

            return $paths;
        });

        Storage::disk(HealthRecordPhoto::DISK)->delete($paths);
    }
}
