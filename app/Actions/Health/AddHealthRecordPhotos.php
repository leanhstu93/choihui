<?php

declare(strict_types=1);

namespace App\Actions\Health;

use App\Exceptions\HuiActionException;
use App\Models\HealthRecord;
use App\Models\HealthRecordPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lưu ảnh đơn thuốc, kết quả cho một lần khám (document/09-gia-dinh.md G11). Tên file do server sinh, không
 * lấy từ người dùng. Lỗi giữa chừng thì xóa các file đã ghi.
 */
final class AddHealthRecordPhotos
{
    /**
     * @param  array<int, UploadedFile>  $files
     *
     * @throws HuiActionException khi vượt số ảnh tối đa.
     */
    public function handle(HealthRecord $record, array $files): void
    {
        if ($files === []) {
            return;
        }

        $stored = [];

        try {
            DB::transaction(function () use ($record, $files, &$stored): void {
                $locked = HealthRecord::query()->lockForUpdate()->findOrFail($record->id);

                if ($locked->photos()->count() + count($files) > HealthRecord::MAX_PHOTOS) {
                    throw new HuiActionException('Mỗi lần khám có tối đa '.HealthRecord::MAX_PHOTOS.' ảnh.');
                }

                foreach ($files as $file) {
                    $path = $file->store("health/{$locked->user_id}", HealthRecordPhoto::DISK);

                    if ($path === false) {
                        throw new HuiActionException('Không lưu được ảnh, vui lòng thử lại.');
                    }

                    $stored[] = $path;
                    $locked->photos()->create(['path' => $path]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk(HealthRecordPhoto::DISK)->delete($stored);

            throw $exception;
        }
    }
}
