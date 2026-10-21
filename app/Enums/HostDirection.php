<?php

declare(strict_types=1);

namespace App\Enums;

enum HostDirection: string
{
    case Thu = 'thu';
    case Chi = 'chi';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::Thu => 'Thu',
            self::Chi => 'Chi',
        };
    }
}
