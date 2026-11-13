<?php

declare(strict_types=1);

namespace App\Actions\Transfer;

use App\Models\TransferRoom;

/**
 * Đóng phòng (một trong hai máy bấm thoát): xóa phòng và mọi tin nhắn còn lại.
 */
final class CloseTransferRoom
{
    public function handle(TransferRoom $room): void
    {
        $room->delete();
    }
}
