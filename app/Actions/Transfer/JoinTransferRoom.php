<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Exceptions\TransferRoomException;
use App\Models\TransferRoom;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Máy thứ hai vào phòng bằng mã. Mỗi phòng chỉ nhận đúng một máy vào; khóa dòng để hai máy
 * cùng vào một lúc không lọt cả hai.
 */
final class JoinTransferRoom
{
    /**
     * @return array{room: TransferRoom, token: string}
     *
     * @throws TransferRoomException
     */
    public function handle(string $code): array
    {
        return DB::transaction(function () use ($code): array {
            $room = TransferRoom::query()->where('code', $code)->lockForUpdate()->first();

            if ($room === null) {
                throw TransferRoomException::notFound();
            }
            if ($room->isExpired()) {
                throw TransferRoomException::expired();
            }
            if ($room->hasReceiver()) {
                throw TransferRoomException::full();
            }

            $token = Str::random(48);
            $room->forceFill([
                'receiver_token_hash' => TransferRoom::hashToken($token),
                'receiver_joined_at' => now(),
                'last_activity_at' => now(),
            ])->save();

            return ['room' => $room, 'token' => $token];
        });
    }
}
