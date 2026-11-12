<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FamilyEvent;
use App\Models\User;

class FamilyEventPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, FamilyEvent $familyEvent): bool
    {
        return $familyEvent->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, FamilyEvent $familyEvent): bool
    {
        return $familyEvent->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, FamilyEvent $familyEvent): bool
    {
        return $familyEvent->isOwnedBy($user);
    }
}
