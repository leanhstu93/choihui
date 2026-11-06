<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GoldItemPhoto;
use App\Models\User;

class GoldItemPhotoPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GoldItemPhoto $goldItemPhoto): bool
    {
        return $goldItemPhoto->isOwnedBy($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GoldItemPhoto $goldItemPhoto): bool
    {
        return $goldItemPhoto->isOwnedBy($user);
    }
}
