<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HostSlot;
use App\Models\HuiGroup;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostSlot>
 */
class HostSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => HuiGroup::factory()->for(User::factory()->host()),
            'user_id' => fn (array $attributes) => HuiGroup::withoutGlobalScopes()->findOrFail($attributes['group_id'])->user_id,
            'slot_no' => fake()->unique()->numberBetween(1, 1000),
            'member_id' => fn (array $attributes) => Member::factory()->state(['user_id' => $attributes['user_id']]),
        ];
    }
}
