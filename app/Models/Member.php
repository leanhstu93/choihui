<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\OwnedByUser;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
     * Hụi viên đã từng giữ tay ở dây nào chưa (kể cả dây đã kết thúc).
     *
     * Bỏ global scope vì truy vấn đã giới hạn theo chính hụi viên này.
     */
    public function hasHeldSlot(): bool
    {
        return $this->hostSlots()->withoutGlobalScope(self::OWNER_SCOPE)->exists();
    }
}
