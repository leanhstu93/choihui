<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskRepeat;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'title' => 'Đóng tiền điện',
            'important' => false,
            'recurrence' => TaskRepeat::None,
            'repeat_weekdays' => 0,
        ];
    }

    public function due(string $date, ?string $time = null): static
    {
        return $this->state(['due_on' => $date, 'due_time' => $time]);
    }

    public function important(): static
    {
        return $this->state(['important' => true]);
    }

    public function done(string $at = '2026-11-18 10:00:00'): static
    {
        return $this->state(['completed_at' => $at]);
    }

    public function repeating(TaskRepeat $recurrence, string $due, int $weekdays = 0, ?int $day = null): static
    {
        return $this->state([
            'due_on' => $due,
            'recurrence' => $recurrence,
            'repeat_weekdays' => $weekdays,
            'repeat_day' => $day,
        ]);
    }
}
