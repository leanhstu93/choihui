<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HostSlot;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class HostSlotPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HostSlot $hostSlot): bool
    {
        return $hostSlot->isOwnedBy($user);
    }

    /**
     * Chỉ đổi người giữ tay (sang tay) khi tay chưa hốt (CH-03).
     */
    public function update(User $user, HostSlot $hostSlot): Response
    {
        if (! $hostSlot->isOwnedBy($user)) {
            return Response::denyAsNotFound();
        }

        return $hostSlot->hasWon()
            ? Response::deny('Tay này đã hốt nên không thể đổi người giữ.')
            : Response::allow();
    }
}
