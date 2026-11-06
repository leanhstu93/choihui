<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoldItem;
use App\Models\GoldSale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoldSale>
 */
class GoldSaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gold_item_id' => GoldItem::factory(),
            'user_id' => fn (array $attributes) => GoldItem::withoutGlobalScopes()->findOrFail($attributes['gold_item_id'])->user_id,
            'weight_ly' => 50,
            'amount' => 8_000_000,
            'sold_on' => '2026-06-01',
        ];
    }
}
