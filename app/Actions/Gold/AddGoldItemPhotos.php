<?php

declare(strict_types=1);

namespace App\Actions\Gold;

use App\Exceptions\HuiActionException;
use App\Models\GoldItem;
use App\Models\GoldItemPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Lưu ảnh cho một món vàng (document/06-vang.md V13). Tên file do server sinh, không
 * lấy từ người dùng. Lỗi giữa chừng thì xóa các file đã ghi.
 */
final class AddGoldItemPhotos
{
    /**
     * @param  array<int, UploadedFile>  $files
     *
     * @throws HuiActionException khi vượt số ảnh tối đa.
     */
    public function handle(GoldItem $item, array $files): void
    {
        if ($files === []) {
            return;
        }

        $stored = [];

        try {
            DB::transaction(function () use ($item, $files, &$stored): void {
                $locked = GoldItem::query()->lockForUpdate()->findOrFail($item->id);

                if ($locked->photos()->count() + count($files) > GoldItem::MAX_PHOTOS) {
                    throw new HuiActionException('Mỗi món có tối đa '.GoldItem::MAX_PHOTOS.' ảnh.');
                }

                foreach ($files as $file) {
                    $path = $file->store("gold/{$locked->user_id}", GoldItemPhoto::DISK);

                    if ($path === false) {
                        throw new HuiActionException('Không lưu được ảnh, vui lòng thử lại.');
                    }

                    $stored[] = $path;
                    $locked->photos()->create(['path' => $path]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk(GoldItemPhoto::DISK)->delete($stored);

            throw $exception;
        }
    }
}
