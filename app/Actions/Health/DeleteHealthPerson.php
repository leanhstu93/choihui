<?php

declare(strict_types=1);

namespace App\Actions\Health;

use App\Models\HealthPerson;
use App\Models\HealthRecordPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Xóa một người cùng mọi lần khám và ảnh (document/09-gia-dinh.md G12). Dòng dữ liệu
 * đã có cascade; file ảnh thì không nên xóa sau khi transaction xong.
 */
final class DeleteHealthPerson
{
    public function handle(HealthPerson $person): void
    {
        $paths = DB::transaction(function () use ($person): array {
            $paths = HealthRecordPhoto::query()
                ->whereIn('health_record_id', $person->records()->select('id'))
                ->pluck('path')
                ->all();
            $person->delete();

            return $paths;
        });

        Storage::disk(HealthRecordPhoto::DISK)->delete($paths);
    }
}
