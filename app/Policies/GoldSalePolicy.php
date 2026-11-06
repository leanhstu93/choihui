<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GoldSale;
use App\Models\User;

class GoldSalePolicy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GoldSale $goldSale): bool
    {
        return $goldSale->isOwnedBy($user);
    }
}
