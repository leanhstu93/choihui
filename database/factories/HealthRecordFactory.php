<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HealthRecordKind;
use App\Models\HealthPerson;
use App\Models\HealthRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthRecord>
 */
class HealthRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'health_person_id' => HealthPerson::factory(),
            'user_id' => fn (array $attributes) => HealthPerson::withoutGlobalScopes()->findOrFail($attributes['health_person_id'])->user_id,
            'visited_on' => '2026-10-01',
            'kind' => HealthRecordKind::Kham,
            'title' => 'Khám tai mũi họng',
            'amount' => 250_000,
        ];
    }
}
