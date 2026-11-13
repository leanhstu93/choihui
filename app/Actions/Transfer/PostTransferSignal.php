<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Enums\TransferRole;
use App\Exceptions\TransferRoomException;
use App\Models\TransferRoom;
use App\Models\TransferSignal;
use Illuminate\Support\Facades\DB;

/**
 * Gửi một tin nhắn bắt tay WebRTC cho máy kia trong phòng.
 */
final class PostTransferSignal
{
    /** Số tin chờ tối đa cho mỗi máy: bắt tay bình thường chỉ vài chục tin. */
    public const MAX_PENDING = 200;

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws TransferRoomException
     */
    public function handle(TransferRoom $room, TransferRole $from, array $payload): TransferSignal
    {
        $recipient = $from->other();

        if ($room->signals()->where('recipient', $recipient)->count() >= self::MAX_PENDING) {
            throw TransferRoomException::tooManySignals();
        }

        return DB::transaction(function () use ($room, $recipient, $payload): TransferSignal {
            $room->forceFill(['last_activity_at' => now()])->save();

            return $room->signals()->create(['recipient' => $recipient, 'payload' => $payload]);
        });
    }
}
