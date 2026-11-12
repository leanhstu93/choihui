<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthPerson;
use App\Models\User;

class HealthPersonPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HealthPerson $healthPerson): bool
    {
        return $healthPerson->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HealthPerson $healthPerson): bool
    {
        return $healthPerson->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HealthPerson $healthPerson): bool
    {
        return $healthPerson->isOwnedBy($user);
    }
}
