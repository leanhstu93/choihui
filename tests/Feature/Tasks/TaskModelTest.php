<?php

declare(strict_types=1);

use App\Enums\TaskRepeat;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;

/*
 * Dữ liệu sổ việc cần làm (document/10-viec-can-lam.md mục 5).
 */

beforeEach(function () {
    $this->travelTo('2026-11-18 09:00:00');
});

test('the database refuses tasks that break the rules', function (array $attributes) {
    $this->actingAs($user = User::factory()->create());

    expect(fn () => Task::factory()->for($user)->create($attributes))->toThrow(QueryException::class);
})->with([
    'time without a date' => [['due_on' => null, 'due_time' => '08:00']],
    'repeat without a date' => [['due_on' => null, 'recurrence' => TaskRepeat::Daily]],
    'weekday bit 8' => [['due_on' => '2026-11-18', 'recurrence' => TaskRepeat::Weekly, 'repeat_weekdays' => 128]],
    'day 32' => [['due_on' => '2026-11-18', 'recurrence' => TaskRepeat::Monthly, 'repeat_day' => 32]],
]);

test('one account cannot see or change another account\'s tasks', function () {
    $this->actingAs($owner = User::factory()->create());
    $task = Task::factory()->for($owner)->create(['title' => 'Bí mật']);
    TaskStep::factory()->for($task)->create();

    $this->actingAs($other = User::factory()->create());

    expect(Task::query()->count())->toBe(0)
        ->and(TaskStep::query()->count())->toBe(0)
        ->and(Gate::forUser($other)->allows('update', $task))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('update', $task))->toBeTrue();
});

test('a new task belongs to the signed-in account', function () {
    $this->actingAs($user = User::factory()->create());

    $task = Task::query()->create(['title' => 'Gọi mẹ']);

    expect($task->user_id)->toBe($user->id)
        ->and($task->fresh()?->recurrence)->toBe(TaskRepeat::None)
        ->and($user->fresh()?->tasks_in_morning_email)->toBeTrue();
});

test('deleting a task deletes its steps, and the spawned link is cleared', function () {
    $this->actingAs($user = User::factory()->create());
    $next = Task::factory()->for($user)->due('2026-11-19')->create();
    $task = Task::factory()->for($user)->done()->create(['spawned_task_id' => $next->id]);
    TaskStep::factory()->count(2)->for($task)->create();

    $next->delete();
    expect($task->fresh()?->spawned_task_id)->toBeNull();

    $task->delete();
    expect(TaskStep::query()->count())->toBe(0);
});

test('time is read as hours and minutes', function () {
    $this->actingAs($user = User::factory()->create());

    expect(Task::factory()->for($user)->due('2026-11-18', '08:30')->create()->fresh()?->due_time)->toBe('08:30');
});

test('overdue only when the due date is before today and the task is open', function (string $due, bool $done, bool $expected) {
    $this->actingAs($user = User::factory()->create());
    $task = Task::factory()->for($user)->due($due)->state(['completed_at' => $done ? now() : null])->create();

    expect($task->isOverdue(now()->toImmutable()))->toBe($expected);
})->with([
    'yesterday' => ['2026-11-17', false, true],
    'today' => ['2026-11-18', false, false],
    'yesterday but done' => ['2026-11-17', true, false],
]);

test('the next due date follows the repeat rule', function () {
    $this->actingAs($user = User::factory()->create());
    $task = Task::factory()->for($user)->repeating(TaskRepeat::MonthlyLunar, '2026-11-23', day: 15)->create();

    expect($task->nextDueOn(now()->toImmutable())?->format('Y-m-d'))->toBe('2026-12-23')
        ->and(Task::factory()->for($user)->create()->nextDueOn(now()->toImmutable()))->toBeNull();
});
