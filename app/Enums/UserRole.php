<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Vai trò của tài khoản (document/04-chu-hui.md C9): mỗi tài khoản chỉ một vai trò.
 */
enum UserRole: string
{
    case HuiVien = 'hui_vien';
    case ChuHui = 'chu_hui';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::HuiVien => 'Hụi viên',
            self::ChuHui => 'Chủ hụi',
        };
    }
}
