<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RepairItem;
use App\Models\RepairRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairRecord>
 */
class RepairRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repair_item_id' => RepairItem::factory(),
            'user_id' => fn (array $attributes) => RepairItem::withoutGlobalScopes()->findOrFail($attributes['repair_item_id'])->user_id,
            'repaired_on' => '2026-01-15',
            'part' => 'Lốp sau',
            'amount' => 900_000,
        ];
    }
}
