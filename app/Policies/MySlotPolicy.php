<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\MySlot;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MySlotPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, MySlot $mySlot): bool
    {
        return $mySlot->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, MySlot $mySlot): bool
    {
        return $mySlot->isOwnedBy($user);
    }

    /**
     * Không cho xóa tay đã hốt, vì kỳ hốt và các dòng tiền đang trỏ tới tay đó.
     */
    public function delete(User $user, MySlot $mySlot): Response
    {
        if (! $mySlot->isOwnedBy($user)) {
            return Response::denyAsNotFound();
        }

        return $mySlot->hasWon()
            ? Response::deny('Tay này đã hốt nên không thể xóa.')
            : Response::allow();
    }
}
