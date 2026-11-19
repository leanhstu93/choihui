<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Task;
use App\Models\TaskStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaskStep>
 */
class TaskStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'user_id' => fn (array $attributes): int => Task::withoutGlobalScopes()->findOrFail($attributes['task_id'])->user_id,
            'title' => 'Chụp hóa đơn',
            'done' => false,
            'position' => 0,
        ];
    }
}
