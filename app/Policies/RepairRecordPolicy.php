<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RepairRecord;
use App\Models\User;

class RepairRecordPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RepairRecord $repairRecord): bool
    {
        return $repairRecord->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RepairRecord $repairRecord): bool
    {
        return $repairRecord->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RepairRecord $repairRecord): bool
    {
        return $repairRecord->isOwnedBy($user);
    }
}
