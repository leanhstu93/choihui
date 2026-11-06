<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\GoldItemPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ảnh của một món vàng, lưu riêng tư, chỉ tải được qua route có kiểm tra chủ sở hữu
 * (document/06-vang.md V13).
 *
 * @property string $id
 * @property int $user_id
 * @property string $gold_item_id
 * @property string $path
 * @property-read GoldItem $item
 */
#[Fillable(['path'])]
class GoldItemPhoto extends Model
{
    /** @use HasFactory<GoldItemPhotoFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    public const DISK = 'local';

    /**
     * @return BelongsTo<GoldItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(GoldItem::class, 'gold_item_id');
    }
}
