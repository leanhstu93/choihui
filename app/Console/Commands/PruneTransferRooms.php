<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TransferRoom;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Xóa phòng gửi file đã hết hạn cùng tin nhắn bắt tay của chúng.
 */
#[Signature('transfer:prune')]
#[Description('Xóa phòng gửi file đã hết hạn')]
class PruneTransferRooms extends Command
{
    public function handle(): int
    {
        $deleted = TransferRoom::query()->expired()->delete();
        $this->info("Đã xóa {$deleted} phòng hết hạn.");

        return self::SUCCESS;
    }
}
