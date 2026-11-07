<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RepairItem;
use App\Models\User;

class RepairItemPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RepairItem $repairItem): bool
    {
        return $repairItem->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RepairItem $repairItem): bool
    {
        return $repairItem->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RepairItem $repairItem): bool
    {
        return $repairItem->isOwnedBy($user);
    }
}
