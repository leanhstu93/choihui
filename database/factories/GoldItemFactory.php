<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GoldType;
use App\Models\GoldItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoldItem>
 */
class GoldItemFactory extends Factory
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
            'weight_ly' => 200,
            'cost_amount' => 30_000_000,
            'bought_on' => '2026-01-15',
        ];
    }
}
