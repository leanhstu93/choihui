<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CashDirection;
use App\Models\CashEntry;
use App\Models\HuiRound;
use App\Models\MySlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashEntry>
 */
class CashEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slot_id' => MySlot::factory(),
            'round_id' => fn (array $attributes) => HuiRound::factory()->state([
                'group_id' => MySlot::withoutGlobalScopes()->findOrFail($attributes['slot_id'])->group_id,
            ]),
            'user_id' => fn (array $attributes) => MySlot::withoutGlobalScopes()->findOrFail($attributes['slot_id'])->user_id,
            'direction' => CashDirection::Dong,
            'amount_expected' => 900_000,
        ];
    }
}
