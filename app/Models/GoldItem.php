<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoldType;
use App\Models\Concerns\OwnedByUser;
use Carbon\CarbonImmutable;
use Database\Factories\GoldItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Một món vàng (document/06-vang.md).
 *
 * @property string $id
 * @property int $user_id
 * @property GoldType $gold_type
 * @property string|null $name
 * @property int $weight_ly
 * @property int $cost_amount
 * @property CarbonImmutable $bought_on
 * @property string|null $shop
 * @property string|null $note
 * @property-read Collection<int, GoldSale> $sales
 * @property-read Collection<int, GoldItemPhoto> $photos
 */
#[Fillable(['gold_type', 'name', 'weight_ly', 'cost_amount', 'bought_on', 'shop', 'note'])]
class GoldItem extends Model
{
    /** @use HasFactory<GoldItemFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    public const MAX_PHOTOS = 5;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gold_type' => GoldType::class,
            'weight_ly' => 'integer',
            'cost_amount' => 'integer',
            'bought_on' => 'immutable_date',
        ];
    }

    /**
     * @return HasMany<GoldSale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(GoldSale::class);
    }

    /**
     * @return HasMany<GoldItemPhoto, $this>
     */
    public function photos(): HasMany
    {
        return $this->hasMany(GoldItemPhoto::class);
    }

    /**
     * Tên hiển thị: tên người dùng đặt, không có thì lấy loại vàng.
     */
    public function displayName(): string
    {
        return $this->name ?: $this->gold_type->label();
    }
}
