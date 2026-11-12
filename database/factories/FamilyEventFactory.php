<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CalendarType;
use App\Enums\FamilyEventKind;
use App\Models\FamilyEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FamilyEvent>
 */
class FamilyEventFactory extends Factory
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
            'title' => 'Sinh nhật mẹ',
            'kind' => FamilyEventKind::SinhNhat,
            'calendar' => CalendarType::Duong,
            'day' => 20,
            'month' => 11,
            'remind_days_before' => 3,
        ];
    }

    public function lunar(int $day, int $month): static
    {
        return $this->state(['calendar' => CalendarType::Am, 'day' => $day, 'month' => $month]);
    }
}
