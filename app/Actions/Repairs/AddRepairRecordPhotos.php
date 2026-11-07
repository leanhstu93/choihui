<?php

declare(strict_types=1);

namespace App\Actions\Repairs;

use App\Exceptions\HuiActionException;
use App\Models\RepairRecord;
use App\Models\RepairRecordPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lưu ảnh hóa đơn cho một lần sửa (document/08-sua-chua.md S5). Tên file do server sinh, không
 * lấy từ người dùng. Lỗi giữa chừng thì xóa các file đã ghi.
 */
final class AddRepairRecordPhotos
{
    /**
     * @param  array<int, UploadedFile>  $files
     *
     * @throws HuiActionException khi vượt số ảnh tối đa.
     */
    public function handle(RepairRecord $record, array $files): void
    {
        if ($files === []) {
            return;
        }

        $stored = [];

        try {
            DB::transaction(function () use ($record, $files, &$stored): void {
                $locked = RepairRecord::query()->lockForUpdate()->findOrFail($record->id);

                if ($locked->photos()->count() + count($files) > RepairRecord::MAX_PHOTOS) {
                    throw new HuiActionException('Mỗi lần sửa có tối đa '.RepairRecord::MAX_PHOTOS.' ảnh.');
                }

                foreach ($files as $file) {
                    $path = $file->store("repairs/{$locked->user_id}", RepairRecordPhoto::DISK);

                    if ($path === false) {
                        throw new HuiActionException('Không lưu được ảnh, vui lòng thử lại.');
                    }

                    $stored[] = $path;
                    $locked->photos()->create(['path' => $path]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk(RepairRecordPhoto::DISK)->delete($stored);

            throw $exception;
        }
    }
}
