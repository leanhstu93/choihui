<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HuiGroupStatus;
use App\Models\HuiGroup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HuiGroup>
 */
class HuiGroupFactory extends Factory
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
            'name' => 'Hụi '.fake()->randomElement([1, 2, 5, 10]).'tr '.fake()->firstName(),
            'face_value' => fake()->randomElement([1_000_000, 2_000_000, 5_000_000]),
            'total_slots' => fake()->numberBetween(6, 15),
            'start_date' => fake()->dateTimeBetween('-6 months', '+1 month')->format('Y-m-d'),
            'due_offset_days' => 0,
            'host_name' => fake()->name(),
            'host_phone' => fake()->numerify('09########'),
        ];
    }

    /**
     * Dây có tiền thảo (số đồng tay hốt phải đưa chủ hụi).
     */
    public function withCommission(int $amount = 1_000_000): static
    {
        return $this->state(fn (array $attributes) => [
            'commission_amount' => $amount,
        ]);
    }

    /**
     * Dây bị bể ở một kỳ.
     */
    public function broken(int $roundNo = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => HuiGroupStatus::Be,
            'broken_round_no' => $roundNo,
        ]);
    }
}
