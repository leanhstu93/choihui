<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HostEntry;
use App\Models\User;

class HostEntryPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HostEntry $hostEntry): bool
    {
        return $hostEntry->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HostEntry $hostEntry): bool
    {
        return $hostEntry->isOwnedBy($user);
    }
}
