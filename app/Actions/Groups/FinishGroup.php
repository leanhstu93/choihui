<?php

declare(strict_types=1);

namespace App\Actions\Groups;

use App\Enums\HuiGroupStatus;
use App\Exceptions\HuiActionException;
use App\Models\HuiGroup;

/**
 * Kết thúc dây đã chạy đủ N kỳ, ẩn khỏi danh sách chính (FR-14).
 */
final class FinishGroup
{
    public function handle(HuiGroup $group): HuiGroup
    {
        if ($group->status !== HuiGroupStatus::DangChay) {
            throw new HuiActionException('Chỉ kết thúc được dây đang chạy.');
        }

        $unrecordedRoundCount = $group->rounds()->whereNull('bid_amount')->count();

        if ($unrecordedRoundCount > 0) {
            throw new HuiActionException("Còn {$unrecordedRoundCount} kỳ chưa nhập kết quả.");
        }

        $group->update(['status' => HuiGroupStatus::KetThuc]);

        return $group;
    }
}
