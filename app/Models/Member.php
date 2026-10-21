<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HuiGroupStatus;
use App\Models\Concerns\OwnedByUser;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hụi viên trong danh bạ của chủ hụi (không phải tài khoản đăng nhập).
 *
 * @property string $id
 * @property int $user_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $note
 * @property-read Collection<int, HostSlot> $hostSlots
 */
#[Fillable(['name', 'phone', 'note'])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @return HasMany<HostSlot, $this>
     */
    public function hostSlots(): HasMany
    {
        return $this->hasMany(HostSlot::class);
    }

    /**
     * Hụi viên đang giữ tay ở một dây đang chạy hay không.
     *
     * Bỏ global scope vì truy vấn đã giới hạn theo chính hụi viên này.
     */
    public function holdsSlotInRunningGroup(): bool
    {
        return $this->hostSlots()
            ->withoutGlobalScope(self::OWNER_SCOPE)
            ->whereHas('group', fn (Builder $query) => $query->withoutGlobalScope(HuiGroup::OWNER_SCOPE)->where('status', HuiGroupStatus::DangChay))
            ->exists();
    }
}
