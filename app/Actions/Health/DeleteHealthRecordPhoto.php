<?php

declare(strict_types=1);

namespace App\Actions\Health;

use App\Models\HealthRecordPhoto;
use Illuminate\Support\Facades\Storage;

/**
 * Xóa một ảnh của lần khám, cả dòng dữ liệu lẫn file.
 */
final class DeleteHealthRecordPhoto
{
    public function handle(HealthRecordPhoto $photo): void
    {
        $photo->delete();
        Storage::disk(HealthRecordPhoto::DISK)->delete($photo->path);
    }
}
