<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AdminAction;
use App\Exceptions\HuiActionException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Khóa tài khoản (document/05-quan-tri.md Q6): đăng xuất mọi phiên, kể cả
 * "ghi nhớ đăng nhập". Không khóa được tài khoản admin.
 */
final class LockUser
{
    public function __construct(private RecordAdminLog $recordAdminLog) {}

    public function handle(User $admin, User $user): User
    {
        if ($user->isAdmin()) {
            throw new HuiActionException('Không khóa được tài khoản quản trị viên.');
        }

        if ($user->isLocked()) {
            return $user;
        }

        DB::transaction(function () use ($admin, $user): void {
            $user->forceFill(['locked_at' => now(), 'remember_token' => Str::random(60)])->save();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            $this->recordAdminLog->handle($admin, AdminAction::Lock, $user);
        });

        return $user;
    }
}
