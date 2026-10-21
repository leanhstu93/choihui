<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashEntryStatus;
use App\Enums\HostDirection;
use App\Models\Concerns\OwnedByUser;
use Database\Factories\HostEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Khoản thu từ một tay hoặc khoản chi cho tay hốt, trong một kỳ của dây chủ hụi.
 *
 * @property string $id
 * @property int $user_id
 * @property string $round_id
 * @property string $host_slot_id
 * @property HostDirection $direction
 * @property int $amount_expected
 * @property int $amount_actual
 * @property Carbon|null $paid_date
 * @property CashEntryStatus $status
 * @property string|null $note
 * @property-read HuiRound $round
 * @property-read HostSlot $hostSlot
 */
#[Fillable(['round_id', 'host_slot_id', 'direction', 'amount_expected', 'amount_actual', 'paid_date', 'status', 'note'])]
class HostEntry extends Model
{
    /** @use HasFactory<HostEntryFactory> */
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
            'direction' => HostDirection::class,
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
     * @return BelongsTo<HostSlot, $this>
     */
    public function hostSlot(): BelongsTo
    {
        return $this->belongsTo(HostSlot::class);
    }
}
