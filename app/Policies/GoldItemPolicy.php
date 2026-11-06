<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GoldItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GoldItemPolicy
{
    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GoldItem $goldItem): bool
    {
        return $goldItem->isOwnedBy($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GoldItem $goldItem): bool
    {
        return $goldItem->isOwnedBy($user);
    }

    /**
     * Không cho xóa món đã có giao dịch bán, để không mất lịch sử (document/06-vang.md V10).
     */
    public function delete(User $user, GoldItem $goldItem): Response
    {
        if (! $goldItem->isOwnedBy($user)) {
            return Response::denyAsNotFound();
        }

        return $goldItem->sales()->exists()
            ? Response::deny('Món này đã có giao dịch bán, hãy xóa giao dịch bán trước.')
            : Response::allow();
    }
}
