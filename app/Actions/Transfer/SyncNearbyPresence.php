<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Models\TransferRoom;
use Illuminate\Support\Str;

/**
 * Máy đang chờ báo "tôi đang mở trang" (gắn vào lượt hỏi tin bắt tay, không thêm request) và nhận
 * lại: danh sách máy cùng mạng, lời mời kết nối đang chờ, ai vừa từ chối lời mời của mình.
 * Chỉ trả tên ngẫu nhiên và loại máy; mã phòng chỉ đưa cho đúng máy được mời.
 */
final class SyncNearbyPresence
{
    /** Ghi lại "vừa thấy" tối đa mỗi ngần này giây, đỡ ghi DB mỗi lượt hỏi. */
    private const TOUCH_SECONDS = 4;

    private const MAX_DEVICES = 20;

    /**
     * @return array{devices: list<array{id: string, name: string, device: string}>, invite: array{id: string, name: string, device: string, code: string}|null, declinedBy: string|null}
     */
    public function handle(TransferRoom $room, ?string $networkHash, string $name, string $device): array
    {
        $empty = ['devices' => [], 'invite' => null, 'declinedBy' => null];

        if ($networkHash === null || $room->hasReceiver()) {
            return $empty;
        }

        $name = Str::limit(trim($name), 37, '…') ?: 'Máy không tên';
        $device = Str::limit(trim($device), 37, '…');
        $declinedBy = $room->invite_declined_at?->gt(now()->subSeconds(30)) ? $room->invite_declined_by : null;
        $changes = [];

        if ($room->nearby_id === null) {
            $changes['nearby_id'] = Str::lower(Str::random(16));
        }
        if ($room->network_hash !== $networkHash || $room->nearby_name !== $name || $room->nearby_device !== $device
            || $room->nearby_seen_at === null || $room->nearby_seen_at->lt(now()->subSeconds(self::TOUCH_SECONDS))) {
            $changes += ['network_hash' => $networkHash, 'nearby_name' => $name, 'nearby_device' => $device, 'nearby_seen_at' => now()];
        }
        if ($room->invite_declined_at !== null) {
            // Báo từ chối một lần là đủ.
            $changes += ['invite_declined_by' => null, 'invite_declined_at' => null];
        }
        if ($changes !== []) {
            $room->forceFill($changes)->save();
        }

        $devices = TransferRoom::query()->nearby($networkHash)
            ->whereKeyNot($room->id)
            ->whereNotNull('nearby_id')
            ->orderBy('created_at')
            ->limit(self::MAX_DEVICES)
            ->get()
            ->map(fn (TransferRoom $other): array => ['id' => (string) $other->nearby_id, 'name' => (string) $other->nearby_name, 'device' => (string) $other->nearby_device])
            ->values()
            ->all();

        return ['devices' => $devices, 'invite' => $this->pendingInvite($room, $networkHash), 'declinedBy' => $declinedBy];
    }

    /**
     * @return array{id: string, name: string, device: string, code: string}|null
     */
    private function pendingInvite(TransferRoom $room, string $networkHash): ?array
    {
        if ($room->invite_from_room_id === null || $room->invite_at === null || $room->invite_at->lt(now()->subSeconds(TransferRoom::INVITE_SECONDS))) {
            return null;
        }

        $inviter = TransferRoom::query()->nearby($networkHash)->whereKey($room->invite_from_room_id)->first();

        return $inviter === null || $inviter->isExpired() ? null : [
            'id' => (string) $inviter->nearby_id,
            'name' => (string) $inviter->nearby_name,
            'device' => (string) $inviter->nearby_device,
            'code' => $inviter->code,
        ];
    }
}
