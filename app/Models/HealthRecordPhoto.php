<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\HealthRecordPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ảnh đơn thuốc / kết quả của một lần khám, lưu riêng tư, chỉ tải được qua route có kiểm
 * tra chủ sở hữu (document/09-gia-dinh.md G11).
 *
 * @property string $id
 * @property int $user_id
 * @property string $health_record_id
 * @property string $path
 * @property-read HealthRecord $record
 */
#[Fillable(['path'])]
class HealthRecordPhoto extends Model
{
    /** @use HasFactory<HealthRecordPhotoFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    public const DISK = 'local';

    /**
     * @return BelongsTo<HealthRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class, 'health_record_id');
    }
}
