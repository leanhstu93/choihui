<?php

declare(strict_types=1);

namespace App\Actions\Repairs;

use App\Models\RepairRecordPhoto;
use Illuminate\Support\Facades\Storage;

/**
 * Xóa một ảnh của lần sửa, cả dòng dữ liệu lẫn file.
 */
final class DeleteRepairRecordPhoto
{
    public function handle(RepairRecordPhoto $photo): void
    {
        $photo->delete();
        Storage::disk(RepairRecordPhoto::DISK)->delete($photo->path);
    }
}
