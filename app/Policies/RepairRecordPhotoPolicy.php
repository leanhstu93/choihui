<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RepairRecordPhoto;
use App\Models\User;

class RepairRecordPhotoPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RepairRecordPhoto $repairRecordPhoto): bool
    {
        return $repairRecordPhoto->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RepairRecordPhoto $repairRecordPhoto): bool
    {
        return $repairRecordPhoto->isOwnedBy($user);
    }
}
