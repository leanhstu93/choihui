<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Phòng ghép đôi hai thiết bị để gửi file trực tiếp. Không có chủ sở hữu (không cần đăng nhập);
 * mỗi thiết bị chứng minh vai trò bằng token riêng, chỉ lưu bản băm.
 *
 * @property string $id
 * @property string $code
 * @property string $sender_token_hash
 * @property string|null $receiver_token_hash
 * @property CarbonImmutable|null $receiver_joined_at
 * @property CarbonImmutable $last_activity_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['code', 'sender_token_hash', 'receiver_token_hash', 'receiver_joined_at', 'last_activity_at'])]
class TransferRoom extends Model
{
    use HasUlids;

    /** Phòng hết hạn sau ngần này phút không có hoạt động. */
    public const IDLE_MINUTES = 10;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receiver_joined_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<TransferSignal, $this>
     */
    public function signals(): HasMany
    {
        return $this->hasMany(TransferSignal::class);
    }

    public function isExpired(): bool
    {
        return $this->last_activity_at->addMinutes(self::IDLE_MINUTES)->isPast();
    }

    public function expiresAt(): CarbonImmutable
    {
        return $this->last_activity_at->addMinutes(self::IDLE_MINUTES);
    }

    public function hasReceiver(): bool
    {
        return $this->receiver_token_hash !== null;
    }

    /**
     * Vai trò ứng với token, hoặc null nếu token không thuộc phòng này.
     */
    public function roleFor(string $token): ?TransferRole
    {
        $hash = self::hashToken($token);

        return match (true) {
            hash_equals($this->sender_token_hash, $hash) => TransferRole::Sender,
            $this->receiver_token_hash !== null && hash_equals($this->receiver_token_hash, $hash) => TransferRole::Receiver,
            default => null,
        };
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('last_activity_at', '<', now()->subMinutes(self::IDLE_MINUTES));
    }
}
