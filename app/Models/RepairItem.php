<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\RepairItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một món đồ trong sổ sửa chữa (document/08-sua-chua.md).
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property bool $tracks_km
 * @property string|null $note
 * @property-read Collection<int, RepairRecord> $records
 */
#[Fillable(['name', 'tracks_km', 'note'])]
class RepairItem extends Model
{
    /** @use HasFactory<RepairItemFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tracks_km' => 'boolean',
        ];
    }

    /**
     * @return HasMany<RepairRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(RepairRecord::class);
    }
}
