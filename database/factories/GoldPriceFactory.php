<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GoldType;
use App\Models\GoldPrice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoldPrice>
 */
class GoldPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gold_type' => GoldType::Nhan9999,
            'price_per_chi' => 15_500_000,
            'priced_on' => '2026-11-06',
        ];
    }
}
