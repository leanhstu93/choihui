<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Transfer\RefillTurnPool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Tạo sẵn mật khẩu TURN cho gửi file, xóa mật khẩu đã hết hạn và cập nhật hạn mức tháng.
 */
#[Signature('transfer:turn-pool')]
#[Description('Giữ sẵn mật khẩu TURN tạm thời cho gửi file')]
class RefillTransferTurnPool extends Command
{
    public function handle(RefillTurnPool $refill): int
    {
        $created = $refill->handle();
        $this->info("Đã tạo {$created} mật khẩu TURN.");

        return self::SUCCESS;
    }
}
