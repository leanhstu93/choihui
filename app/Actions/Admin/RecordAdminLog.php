<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\AdminAction;
use App\Models\AdminLog;
use App\Models\User;

/**
 * Ghi một dòng nhật ký quản trị (document/05-quan-tri.md QT-09).
 */
final class RecordAdminLog
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function handle(User $admin, AdminAction $action, ?User $user = null, array $meta = []): AdminLog
    {
        return AdminLog::query()->create([
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'user_id' => $user?->id,
            'action' => $action,
            'meta' => $meta === [] ? null : $meta,
        ]);
    }
}
