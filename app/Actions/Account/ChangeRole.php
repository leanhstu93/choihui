<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Enums\UserRole;
use App\Exceptions\HuiActionException;
use App\Models\HuiGroup;
use App\Models\User;

/**
 * Đổi vai trò tài khoản (document/04-chu-hui.md C9): chỉ khi chưa có dây nào, vì
 * dây của hụi viên và dây của chủ hụi lưu dữ liệu khác nhau.
 */
final class ChangeRole
{
    public function handle(User $user, UserRole $role): User
    {
        $groupCount = HuiGroup::query()->withoutGlobalScope(HuiGroup::OWNER_SCOPE)->where('user_id', $user->id)->count();

        if ($groupCount > 0) {
            throw new HuiActionException('Chỉ đổi được vai trò khi tài khoản chưa có dây hụi nào.');
        }

        $user->forceFill(['role' => $role])->save();

        return $user;
    }
}
