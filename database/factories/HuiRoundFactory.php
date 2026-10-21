<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WinnerType;
use App\Models\HuiGroup;
use App\Models\HuiRound;
use App\Models\MySlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HuiRound>
 */
class HuiRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => HuiGroup::factory(),
            'user_id' => fn (array $attributes) => HuiGroup::withoutGlobalScopes()->findOrFail($attributes['group_id'])->user_id,
            'round_no' => fake()->unique()->numberBetween(1, 1000),
            'open_date' => fake()->date(),
        ];
    }

    /**
     * Kỳ do một tay của mình hốt, với lãi thăm đã nhập.
     */
    public function wonBy(MySlot $slot, int $bidAmount = 100_000): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $slot->group_id,
            'user_id' => $slot->user_id,
            'bid_amount' => $bidAmount,
            'winner_type' => WinnerType::Minh,
            'winner_slot_id' => $slot->id,
        ]);
    }
}
