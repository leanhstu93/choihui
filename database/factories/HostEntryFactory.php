<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HostDirection;
use App\Models\HostEntry;
use App\Models\HostSlot;
use App\Models\HuiRound;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HostEntry>
 */
class HostEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'host_slot_id' => HostSlot::factory(),
            'round_id' => fn (array $attributes) => HuiRound::factory()->state([
                'group_id' => HostSlot::withoutGlobalScopes()->findOrFail($attributes['host_slot_id'])->group_id,
            ]),
            'user_id' => fn (array $attributes) => HostSlot::withoutGlobalScopes()->findOrFail($attributes['host_slot_id'])->user_id,
            'direction' => HostDirection::Thu,
            'amount_expected' => 900_000,
        ];
    }
}
