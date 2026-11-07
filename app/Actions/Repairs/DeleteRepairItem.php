<?php

declare(strict_types=1);

namespace App\Actions\Repairs;

use App\Models\RepairItem;
use App\Models\RepairRecordPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Xóa món đồ cùng mọi lần sửa và ảnh của nó (document/08-sua-chua.md S6). Dòng dữ liệu
 * đã có cascade; file ảnh thì không nên xóa sau khi transaction xong.
 */
final class DeleteRepairItem
{
    public function handle(RepairItem $item): void
    {
        $paths = DB::transaction(function () use ($item): array {
            $paths = RepairRecordPhoto::query()
                ->whereIn('repair_record_id', $item->records()->select('id'))
                ->pluck('path')
                ->all();
            $item->delete();

            return $paths;
        });

        Storage::disk(RepairRecordPhoto::DISK)->delete($paths);
    }
}
