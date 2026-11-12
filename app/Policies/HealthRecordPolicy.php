<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthRecord;
use App\Models\User;

class HealthRecordPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HealthRecord $healthRecord): bool
    {
        return $healthRecord->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, HealthRecord $healthRecord): bool
    {
        return $healthRecord->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HealthRecord $healthRecord): bool
    {
        return $healthRecord->isOwnedBy($user);
    }
}
