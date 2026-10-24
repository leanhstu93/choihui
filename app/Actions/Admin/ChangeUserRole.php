<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Actions\Account\ChangeRole;
use App\Enums\AdminAction;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin đổi vai trò hộ người dùng (document/05-quan-tri.md Q8): cùng quy tắc
 * với người dùng tự đổi, chỉ khi chưa có dây nào.
 */
final class ChangeUserRole
{
    public function __construct(private ChangeRole $changeRole, private RecordAdminLog $recordAdminLog) {}

    public function handle(User $admin, User $user, UserRole $role): User
    {
        if ($user->role === $role) {
            return $user;
        }

        $from = $user->role;

        return DB::transaction(function () use ($admin, $user, $role, $from): User {
            $this->changeRole->handle($user, $role);
            $this->recordAdminLog->handle($admin, AdminAction::ChangeRole, $user, ['from' => $from->value, 'to' => $role->value]);

            return $user;
        });
    }
}
