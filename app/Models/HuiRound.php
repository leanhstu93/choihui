<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WinnerType;
use App\Models\Concerns\OwnedByUser;
use Database\Factories\HuiRoundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $group_id
 * @property int $round_no
 * @property Carbon $open_date
 * @property int|null $bid_amount
 * @property WinnerType $winner_type
 * @property string|null $winner_slot_id
 * @property string|null $winner_name
 * @property-read HuiGroup $group
 * @property-read MySlot|null $winnerSlot
 * @property-read Collection<int, CashEntry> $cashEntries
 */
#[Fillable(['group_id', 'round_no', 'open_date', 'bid_amount', 'winner_type', 'winner_slot_id', 'winner_name'])]
class HuiRound extends Model
{
    /** @use HasFactory<HuiRoundFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'winner_type' => 'chua_co',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'round_no' => 'integer',
            'open_date' => 'date',
            'bid_amount' => 'integer',
            'winner_type' => WinnerType::class,
        ];
    }

    /**
     * @return BelongsTo<HuiGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(HuiGroup::class, 'group_id');
    }

    /**
     * @return BelongsTo<MySlot, $this>
     */
    public function winnerSlot(): BelongsTo
    {
        return $this->belongsTo(MySlot::class, 'winner_slot_id');
    }

    /**
     * @return HasMany<CashEntry, $this>
     */
    public function cashEntries(): HasMany
    {
        return $this->hasMany(CashEntry::class, 'round_id');
    }
}
