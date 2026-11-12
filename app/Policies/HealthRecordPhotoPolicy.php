<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\HealthRecordPhoto;
use App\Models\User;

class HealthRecordPhotoPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, HealthRecordPhoto $healthRecordPhoto): bool
    {
        return $healthRecordPhoto->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, HealthRecordPhoto $healthRecordPhoto): bool
    {
        return $healthRecordPhoto->isOwnedBy($user);
    }
}
