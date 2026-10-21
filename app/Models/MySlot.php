<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\MySlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property int $user_id
 * @property string $group_id
 * @property string $label
 * @property-read HuiGroup $group
 */
#[Fillable(['group_id', 'label'])]
class MySlot extends Model
{
    /** @use HasFactory<MySlotFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @return BelongsTo<HuiGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(HuiGroup::class, 'group_id');
    }

    /**
     * Kỳ mà tay này đã hốt (mỗi tay hốt tối đa một lần).
     *
     * @return HasOne<HuiRound, $this>
     */
    public function wonRound(): HasOne
    {
        return $this->hasOne(HuiRound::class, 'winner_slot_id');
    }

    /**
     * @return HasMany<CashEntry, $this>
     */
    public function cashEntries(): HasMany
    {
        return $this->hasMany(CashEntry::class, 'slot_id');
    }

    /**
     * Tay đã hốt ở một kỳ nào đó hay chưa.
     *
     * Bỏ global scope vì truy vấn đã giới hạn theo chính tay này, và Policy có
     * thể gọi hàm này khi không có ai đăng nhập (ví dụ lệnh chạy theo lịch).
     */
    public function hasWon(): bool
    {
        return $this->wonRound()->withoutGlobalScope(self::OWNER_SCOPE)->exists();
    }
}
