<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Models\TransferRoom;

/**
 * Từ chối lời mời kết nối: xóa lời mời và báo cho máy mời biết.
 */
final class DeclineNearbyInvite
{
    public function handle(TransferRoom $room): void
    {
        if ($room->invite_from_room_id !== null) {
            TransferRoom::query()->whereKey($room->invite_from_room_id)->update([
                'invite_declined_by' => $room->nearby_name ?? 'Máy kia',
                'invite_declined_at' => now(),
            ]);
        }

        $room->forceFill(['invite_from_room_id' => null, 'invite_at' => null])->save();
    }
}
