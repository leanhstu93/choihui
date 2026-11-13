<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Một mật khẩu TURN tạm thời (Open Relay / Metered). Tạo sẵn trong kho, giao cho đúng một phòng
 * (cả hai máy trong phòng dùng chung), hết hạn cứng tại expires_at. Danh sách máy chủ ICE được
 * mã hóa khi lưu.
 *
 * @property string $id
 * @property string $username
 * @property list<array<string, mixed>> $ice_servers
 * @property CarbonImmutable $ready_at
 * @property CarbonImmutable $expires_at
 * @property string|null $transfer_room_id
 * @property CarbonImmutable|null $issued_at
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['username', 'ice_servers', 'ready_at', 'expires_at', 'transfer_room_id', 'issued_at'])]
class TransferTurnCredential extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ice_servers' => 'encrypted:array',
            'ready_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'issued_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Còn trong kho, chưa giao, và còn đủ lâu để một phiên gửi xong trước khi hết hạn cứng.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        $minutes = (int) config('services.transfer.turn_min_remaining_minutes');

        return $query->whereNull('issued_at')->where('expires_at', '>', now()->addMinutes($minutes));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Số giây còn phải chờ mật khẩu lan ra các máy chủ TURN (0 là dùng được ngay).
     */
    public function secondsUntilReady(): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($this->ready_at, false)));
    }
}
