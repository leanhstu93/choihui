<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\HostSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Một trong N tay của dây chủ hụi, do một hụi viên giữ.
 *
 * @property string $id
 * @property int $user_id
 * @property string $group_id
 * @property int $slot_no
 * @property string $member_id
 * @property-read HuiGroup $group
 * @property-read Member $member
 * @property-read HuiRound|null $wonRound
 * @property-read Collection<int, HostEntry> $hostEntries
 */
#[Fillable(['group_id', 'slot_no', 'member_id'])]
class HostSlot extends Model
{
    /** @use HasFactory<HostSlotFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_no' => 'integer',
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
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Kỳ mà tay này đã hốt (mỗi tay hốt tối đa một lần).
     *
     * @return HasOne<HuiRound, $this>
     */
    public function wonRound(): HasOne
    {
        return $this->hasOne(HuiRound::class, 'winner_host_slot_id');
    }

    /**
     * @return HasMany<HostEntry, $this>
     */
    public function hostEntries(): HasMany
    {
        return $this->hasMany(HostEntry::class);
    }

    /**
     * Tay đã hốt ở một kỳ nào đó hay chưa.
     *
     * Bỏ global scope vì truy vấn đã giới hạn theo chính tay này.
     */
    public function hasWon(): bool
    {
        return $this->wonRound()->withoutGlobalScope(self::OWNER_SCOPE)->exists();
    }
}
