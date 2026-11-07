<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Carbon\CarbonImmutable;
use Database\Factories\RepairRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một lần sửa, thay hoặc bảo dưỡng món đồ (document/08-sua-chua.md).
 *
 * @property string $id
 * @property int $user_id
 * @property string $repair_item_id
 * @property CarbonImmutable $repaired_on
 * @property string $part
 * @property int $amount
 * @property string|null $place
 * @property CarbonImmutable|null $warranty_until
 * @property int|null $odometer_km
 * @property string|null $note
 * @property-read RepairItem $item
 * @property-read Collection<int, RepairRecordPhoto> $photos
 */
#[Fillable(['repaired_on', 'part', 'amount', 'place', 'warranty_until', 'odometer_km', 'note'])]
class RepairRecord extends Model
{
    /** @use HasFactory<RepairRecordFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    public const MAX_PHOTOS = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'repaired_on' => 'immutable_date',
            'amount' => 'integer',
            'warranty_until' => 'immutable_date',
            'odometer_km' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<RepairItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(RepairItem::class, 'repair_item_id');
    }

    /**
     * @return HasMany<RepairRecordPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(RepairRecordPhoto::class);
    }

    /**
     * Ngày cuối bảo hành vẫn tính là còn bảo hành.
     */
    public function isUnderWarranty(CarbonImmutable $today): bool
    {
        return $this->warranty_until !== null && $this->warranty_until->greaterThanOrEqualTo($today);
    }
}
