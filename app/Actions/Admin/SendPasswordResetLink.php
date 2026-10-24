<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AdminAction;
use App\Exceptions\HuiActionException;
use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Gửi email đặt lại mật khẩu cho người dùng (document/05-quan-tri.md Q7):
 * admin không bao giờ biết hay đặt mật khẩu hộ.
 */
final class SendPasswordResetLink
{
    public function __construct(private RecordAdminLog $recordAdminLog) {}

    public function handle(User $admin, User $user): void
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_THROTTLED) {
            throw new HuiActionException('Vừa gửi email đặt lại mật khẩu cho tài khoản này. Vui lòng đợi một lát rồi gửi lại.');
        }

        if ($status !== Password::RESET_LINK_SENT) {
            throw new HuiActionException('Không gửi được email đặt lại mật khẩu.');
        }

        $this->recordAdminLog->handle($admin, AdminAction::SendPasswordReset, $user);
    }
}
