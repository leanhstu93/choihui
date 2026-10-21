<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HuiFrequency;
use App\Enums\HuiGroupStatus;
use App\Enums\HuiGroupType;
use App\Models\Concerns\OwnedByUser;
use Database\Factories\HuiGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property HuiGroupType $type
 * @property HuiFrequency $frequency
 * @property int $face_value
 * @property int $total_slots
 * @property Carbon $start_date
 * @property int $due_offset_days
 * @property string $host_name
 * @property string|null $host_phone
 * @property int $commission_amount
 * @property HuiGroupStatus $status
 * @property int|null $broken_round_no
 * @property string|null $note
 * @property-read Collection<int, MySlot> $slots
 * @property-read Collection<int, HuiRound> $rounds
 * @property-read Collection<int, HostSlot> $hostSlots
 */
#[Fillable([
    'name', 'type', 'frequency', 'face_value', 'total_slots', 'start_date', 'due_offset_days',
    'host_name', 'host_phone', 'commission_amount', 'status', 'broken_round_no', 'note',
])]
class HuiGroup extends Model
{
    /** @use HasFactory<HuiGroupFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'co_lai',
        'frequency' => 'thang',
        'due_offset_days' => 0,
        'commission_amount' => 0,
        'status' => 'dang_chay',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => HuiGroupType::class,
            'frequency' => HuiFrequency::class,
            'face_value' => 'integer',
            'total_slots' => 'integer',
            'start_date' => 'date',
            'due_offset_days' => 'integer',
            'commission_amount' => 'integer',
            'status' => HuiGroupStatus::class,
            'broken_round_no' => 'integer',
        ];
    }

    /**
     * @return HasMany<MySlot, $this>
     */
    public function slots(): HasMany
    {
        return $this->hasMany(MySlot::class, 'group_id');
    }

    /**
     * N tay của dây chủ hụi, theo số thứ tự tay.
     *
     * @return HasMany<HostSlot, $this>
     */
    public function hostSlots(): HasMany
    {
        return $this->hasMany(HostSlot::class, 'group_id')->orderBy('slot_no');
    }

    /**
     * @return HasMany<HuiRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(HuiRound::class, 'group_id')->orderBy('round_no');
    }

    /**
     * Khoản thu / chi của dây chủ hụi.
     *
     * @return HasManyThrough<HostEntry, HuiRound, $this>
     */
    public function hostEntries(): HasManyThrough
    {
        return $this->hasManyThrough(HostEntry::class, HuiRound::class, 'group_id', 'round_id');
    }

    /**
     * @return HasManyThrough<CashEntry, HuiRound, $this>
     */
    public function cashEntries(): HasManyThrough
    {
        return $this->hasManyThrough(CashEntry::class, HuiRound::class, 'group_id', 'round_id');
    }
}
