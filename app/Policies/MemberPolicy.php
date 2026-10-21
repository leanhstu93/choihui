<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Member;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MemberPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Member $member): bool
    {
        return $member->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Member $member): bool
    {
        return $member->isOwnedBy($user);
    }

    /**
     * Không cho xóa hụi viên đang giữ tay ở dây đang chạy (CH-02).
     */
    public function delete(User $user, Member $member): Response
    {
        if (! $member->isOwnedBy($user)) {
            return Response::denyAsNotFound();
        }

        return $member->holdsSlotInRunningGroup()
            ? Response::deny('Hụi viên đang giữ tay ở dây đang chạy nên không thể xóa.')
            : Response::allow();
    }
}
