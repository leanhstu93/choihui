<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashDirection;
use App\Enums\CashEntryStatus;
use App\Models\Concerns\OwnedByUser;
use Database\Factories\CashEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $round_id
 * @property string $slot_id
 * @property CashDirection $direction
 * @property int $amount_expected
 * @property int $amount_actual
 * @property Carbon|null $paid_date
 * @property CashEntryStatus $status
 * @property string|null $note
 * @property-read HuiRound $round
 * @property-read MySlot $slot
 */
#[Fillable(['round_id', 'slot_id', 'direction', 'amount_expected', 'amount_actual', 'paid_date', 'status', 'note'])]
class CashEntry extends Model
{
    /** @use HasFactory<CashEntryFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'amount_actual' => 0,
        'status' => 'chua',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => CashDirection::class,
            'amount_expected' => 'integer',
            'amount_actual' => 'integer',
            'paid_date' => 'date',
            'status' => CashEntryStatus::class,
        ];
    }

    /**
     * @return BelongsTo<HuiRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(HuiRound::class, 'round_id');
    }

    /**
     * @return BelongsTo<MySlot, $this>
     */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(MySlot::class, 'slot_id');
    }
}
