<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Thao tác được ghi vào nhật ký quản trị (document/05-quan-tri.md QT-09).
 */
enum AdminAction: string
{
    case ViewUser = 'view_user';
    case ViewGroup = 'view_group';
    case Lock = 'lock';
    case Unlock = 'unlock';
    case SendPasswordReset = 'send_password_reset';
    case ChangeRole = 'change_role';

    /**
     * Nhãn tiếng Việt để hiển thị.
     */
    public function label(): string
    {
        return match ($this) {
            self::ViewUser => 'Xem tài khoản',
            self::ViewGroup => 'Xem dây hụi',
            self::Lock => 'Khóa tài khoản',
            self::Unlock => 'Mở khóa tài khoản',
            self::SendPasswordReset => 'Gửi email đặt lại mật khẩu',
            self::ChangeRole => 'Đổi vai trò',
        };
    }
}
