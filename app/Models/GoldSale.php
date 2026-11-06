<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Carbon\CarbonImmutable;
use Database\Factories\GoldSaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần bán toàn bộ hoặc một phần món vàng (document/06-vang.md VG-04).
 *
 * @property string $id
 * @property int $user_id
 * @property string $gold_item_id
 * @property int $weight_ly
 * @property int $amount
 * @property CarbonImmutable $sold_on
 * @property string|null $note
 * @property-read GoldItem $item
 */
#[Fillable(['weight_ly', 'amount', 'sold_on', 'note'])]
class GoldSale extends Model
{
    /** @use HasFactory<GoldSaleFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight_ly' => 'integer',
            'amount' => 'integer',
            'sold_on' => 'immutable_date',
        ];
    }

    /**
     * @return BelongsTo<GoldItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(GoldItem::class, 'gold_item_id');
    }
}
