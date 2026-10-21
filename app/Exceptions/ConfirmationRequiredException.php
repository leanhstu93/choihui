<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thao tác sẽ thay đổi những dòng tiền đã có tiền thực tế. Giao diện hỏi lại
 * người dùng rồi gọi lại Action với $confirmed = true.
 */
class ConfirmationRequiredException extends HuiActionException
{
    public function __construct(public readonly int $affectedEntryCount)
    {
        parent::__construct("Thay đổi này ảnh hưởng {$affectedEntryCount} khoản đã ghi nhận tiền. Bạn có chắc muốn cập nhật?");
    }
}
