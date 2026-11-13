<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Vai trò của một thiết bị trong phòng gửi file: người tạo phòng và người vào phòng.
 */
enum TransferRole: string
{
    case Sender = 'sender';
    case Receiver = 'receiver';

    public function other(): self
    {
        return $this === self::Sender ? self::Receiver : self::Sender;
    }

    public function label(): string
    {
        return $this === self::Sender ? 'Máy tạo phòng' : 'Máy vào phòng';
    }
}
