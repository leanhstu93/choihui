<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Exceptions\TransferRoomException;
use App\Models\TransferRoom;

/**
 * Mời một máy cùng mạng kết nối với máy này. Máy kia thấy lời mời ở lượt hỏi tiếp theo và phải
 * bấm Đồng ý; đồng ý thì nó vào phòng của máy mời như khi nhập mã.
 */
final class InviteNearbyDevice
{
    /**
     * @throws TransferRoomException
     */
    public function handle(TransferRoom $from, ?string $networkHash, string $targetId): void
    {
        $target = $networkHash === null ? null : TransferRoom::query()->nearby($networkHash)
            ->where('nearby_id', $targetId)
            ->whereKeyNot($from->id)
            ->first();

        if ($target === null || $target->isExpired() || $from->hasReceiver() || $from->network_hash !== $networkHash) {
            throw TransferRoomException::nearbyGone();
        }

        $target->forceFill(['invite_from_room_id' => $from->id, 'invite_at' => now()])->save();
        $from->forceFill(['invite_declined_by' => null, 'invite_declined_at' => null])->save();
    }
}
