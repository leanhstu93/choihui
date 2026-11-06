<?php

declare(strict_types=1);

namespace App\Actions\NetWorth;

use App\Models\NetWorthSnapshot;

/**
 * Ghi số tổng tài sản của tháng hiện tại; mở lại trong tháng thì ghi đè
 * (document/07-tong-tai-san.md T5).
 */
final class RecordNetWorthSnapshot
{
    public function handle(int $assets, int $debts): NetWorthSnapshot
    {
        return NetWorthSnapshot::query()->updateOrCreate(
            ['month' => now()->format('Y-m')],
            ['assets' => $assets, 'debts' => $debts],
        );
    }
}
