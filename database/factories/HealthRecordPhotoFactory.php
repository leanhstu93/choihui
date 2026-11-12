<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\HealthRecord;
use App\Models\HealthRecordPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthRecordPhoto>
 */
class HealthRecordPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'health_record_id' => HealthRecord::factory(),
            'user_id' => fn (array $attributes) => HealthRecord::withoutGlobalScopes()->findOrFail($attributes['health_record_id'])->user_id,
            'path' => fn (array $attributes) => "health/{$attributes['user_id']}/".fake()->uuid().'.jpg',
        ];
    }
}
