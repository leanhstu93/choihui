<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AdminAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mở khóa tài khoản (document/05-quan-tri.md QT-05).
 */
final class UnlockUser
{
    public function __construct(private RecordAdminLog $recordAdminLog) {}

    public function handle(User $admin, User $user): User
    {
        if (! $user->isLocked()) {
            return $user;
        }

        DB::transaction(function () use ($admin, $user): void {
            $user->forceFill(['locked_at' => null])->save();
            $this->recordAdminLog->handle($admin, AdminAction::Unlock, $user);
        });

        return $user;
    }
}
