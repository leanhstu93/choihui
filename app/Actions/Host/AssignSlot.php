<?php

declare(strict_types=1);

namespace App\Actions\Host;

use App\Enums\HuiGroupStatus;
use App\Exceptions\HuiActionException;
use App\Models\HostSlot;
use App\Models\Member;

/**
 * Đổi hụi viên giữ một tay (sang tay, CH-03). Chỉ khi tay chưa hốt và dây đang chạy;
 * các khoản đã thu trước đó vẫn gắn với tay, không đổi.
 */
final class AssignSlot
{
    public function handle(HostSlot $hostSlot, Member $member): HostSlot
    {
        if ($hostSlot->group->status !== HuiGroupStatus::DangChay) {
            throw new HuiActionException('Chỉ đổi người giữ tay khi dây đang chạy.');
        }

        if ($hostSlot->hasWon()) {
            throw new HuiActionException('Tay này đã hốt nên không thể đổi người giữ.');
        }

        $hostSlot->update(['member_id' => $member->id]);

        return $hostSlot;
    }
}
