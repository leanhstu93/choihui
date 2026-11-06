<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoldType;
use App\Models\Concerns\OwnedByUser;
use Carbon\CarbonImmutable;
use Database\Factories\GoldPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Giá tiệm mua lại do người dùng tự nhập, mỗi loại vàng một dòng (document/06-vang.md V7).
 *
 * @property string $id
 * @property int $user_id
 * @property GoldType $gold_type
 * @property int $price_per_chi
 * @property CarbonImmutable $priced_on
 */
#[Fillable(['gold_type', 'price_per_chi', 'priced_on'])]
class GoldPrice extends Model
{
    /** @use HasFactory<GoldPriceFactory> */
    use HasFactory, HasUlids, OwnedByUser;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gold_type' => GoldType::class,
            'price_per_chi' => 'integer',
            'priced_on' => 'immutable_date',
        ];
    }
}
