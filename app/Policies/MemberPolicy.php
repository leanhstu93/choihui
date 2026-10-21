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
     * Không cho xóa hụi viên đã từng giữ tay, để các dây cũ còn đủ lịch sử (CH-02).
     */
    public function delete(User $user, Member $member): Response
    {
        if (! $member->isOwnedBy($user)) {
            return Response::denyAsNotFound();
        }

        return $member->hasHeldSlot()
            ? Response::deny('Hụi viên đã giữ tay trong dây nên không thể xóa.')
            : Response::allow();
    }
}
