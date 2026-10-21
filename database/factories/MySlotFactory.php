<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HuiGroup;
use App\Models\MySlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MySlot>
 */
class MySlotFactory extends Factory
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
            'label' => 'Tay '.fake()->unique()->numberBetween(1, 1000),
        ];
    }
}
