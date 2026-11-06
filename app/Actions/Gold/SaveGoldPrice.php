<?php

declare(strict_types=1);

namespace App\Actions\Gold;

use App\Enums\GoldType;
use App\Models\GoldPrice;

/**
 * Lưu giá hiện tại của một loại vàng (document/06-vang.md VG-06). Luôn ghi lại ngày
 * nhập, kể cả khi giá không đổi, để người dùng biết giá vừa được xác nhận.
 */
final class SaveGoldPrice
{
    public function handle(GoldType $type, int $pricePerChi): GoldPrice
    {
        return GoldPrice::query()->updateOrCreate(
            ['gold_type' => $type],
            ['price_per_chi' => $pricePerChi, 'priced_on' => now()->toDateString()],
        );
    }
}
