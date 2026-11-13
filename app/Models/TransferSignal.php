<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransferRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một tin nhắn bắt tay WebRTC (offer, answer, ICE candidate) chờ thiết bị kia lấy. Xóa ngay khi
 * thiết bị nhận báo đã lấy, hoặc khi phòng hết hạn.
 *
 * @property int $id
 * @property string $transfer_room_id
 * @property TransferRole $recipient
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['transfer_room_id', 'recipient', 'payload'])]
class TransferSignal extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recipient' => TransferRole::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<TransferRoom, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(TransferRoom::class, 'transfer_room_id');
    }
}
