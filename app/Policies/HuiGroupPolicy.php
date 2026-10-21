<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HuiGroup;
use App\Models\User;

class HuiGroupPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HuiGroup $huiGroup): bool
    {
        return $huiGroup->isOwnedBy($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HuiGroup $huiGroup): bool
    {
        return $huiGroup->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HuiGroup $huiGroup): bool
    {
        return $huiGroup->isOwnedBy($user);
    }
}
