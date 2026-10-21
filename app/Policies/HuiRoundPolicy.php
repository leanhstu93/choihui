<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HuiRound;
use App\Models\User;

/**
 * Các kỳ do hệ thống tự sinh khi tạo dây, người dùng chỉ xem và nhập kết quả.
 */
class HuiRoundPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HuiRound $huiRound): bool
    {
        return $huiRound->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HuiRound $huiRound): bool
    {
        return $huiRound->isOwnedBy($user);
    }
}
