<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\RepairRecordPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ảnh hóa đơn của một lần sửa, lưu riêng tư, chỉ tải được qua route có kiểm tra chủ sở
 * hữu (document/08-sua-chua.md S5).
 *
 * @property string $id
 * @property int $user_id
 * @property string $repair_record_id
 * @property string $path
 * @property-read RepairRecord $record
 */
#[Fillable(['path'])]
class RepairRecordPhoto extends Model
{
    /** @use HasFactory<RepairRecordPhotoFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    public const DISK = 'local';

    /**
     * @return BelongsTo<RepairRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(RepairRecord::class, 'repair_record_id');
    }
}
