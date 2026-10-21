<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CashEntry;
use App\Models\User;

class CashEntryPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CashEntry $cashEntry): bool
    {
        return $cashEntry->isOwnedBy($user);
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
    public function update(User $user, CashEntry $cashEntry): bool
    {
        return $cashEntry->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CashEntry $cashEntry): bool
    {
        return $cashEntry->isOwnedBy($user);
    }
}
