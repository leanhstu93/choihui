<?php

declare(strict_types=1);

namespace App\Actions\Gold;

use App\Models\GoldItemPhoto;
use Illuminate\Support\Facades\Storage;

/**
 * Xóa một ảnh của món vàng, cả dòng dữ liệu lẫn file.
 */
final class DeleteGoldItemPhoto
{
    public function handle(GoldItemPhoto $photo): void
    {
        $photo->delete();
        Storage::disk(GoldItemPhoto::DISK)->delete($photo->path);
    }
}
