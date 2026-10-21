<?php

declare(strict_types=1);

namespace App\Actions\Members;

use App\Exceptions\HuiActionException;
use App\Models\Member;

/**
 * Xóa hụi viên khỏi danh bạ (CH-02). Người đã từng giữ tay thì giữ lại để các
 * dây cũ vẫn còn đủ lịch sử.
 */
final class DeleteMember
{
    public function handle(Member $member): void
    {
        if ($member->hasHeldSlot()) {
            throw new HuiActionException('Hụi viên đã giữ tay trong dây nên không thể xóa.');
        }

        $member->delete();
    }
}
