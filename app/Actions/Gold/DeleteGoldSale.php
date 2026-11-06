<?php

declare(strict_types=1);

namespace App\Actions\Gold;

use App\Models\GoldSale;

/**
 * Xóa một giao dịch bán; khối lượng tự trở lại món vàng vì phần còn lại luôn được
 * tính từ các giao dịch (document/06-vang.md VG-05).
 */
final class DeleteGoldSale
{
    public function handle(GoldSale $sale): void
    {
        $sale->delete();
    }
}
