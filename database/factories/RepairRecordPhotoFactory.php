<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RepairRecord;
use App\Models\RepairRecordPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepairRecordPhoto>
 */
class RepairRecordPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'repair_record_id' => RepairRecord::factory(),
            'user_id' => fn (array $attributes) => RepairRecord::withoutGlobalScopes()->findOrFail($attributes['repair_record_id'])->user_id,
            'path' => fn (array $attributes) => "repairs/{$attributes['user_id']}/".fake()->uuid().'.jpg',
        ];
    }
}
