<?php

declare(strict_types=1);

use App\Actions\Backup\RestoreBackup;
use App\Enums\TaskRepeat;
use App\Exceptions\HuiActionException;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use App\Queries\UserBackup;

/*
 * Sao lưu / khôi phục và xóa tài khoản với sổ việc (document/10-viec-can-lam.md V15).
 */

beforeEach(function () {
    $this->travelTo('2026-11-18 09:00:00');
});

/**
 * @return array<string, mixed>
 */
function exportedBackup(User $user): array
{
    return json_decode((string) json_encode(app(UserBackup::class)->export($user)), true);
}

test('a backup carries tasks and their steps into another account', function () {
    $this->actingAs($alice = User::factory()->create());
    $task = Task::factory()->for($alice)->repeating(TaskRepeat::Weekly, '2026-11-19', 0b0001001)->important()->create(['title' => 'Đổ rác', 'due_time' => '07:00', 'note' => 'Thùng xanh']);
    TaskStep::factory()->for($task)->create(['title' => 'Rác tái chế', 'done' => true, 'position' => 3]);
    Task::factory()->for($alice)->done('2026-11-17 20:00:00')->create(['title' => 'Đi chợ']);
    $backup = exportedBackup($alice);

    $this->actingAs($bob = User::factory()->create());
    Task::factory()->for($bob)->create(['title' => 'Việc cũ của Bob']);
    $stats = app(RestoreBackup::class)->handle($bob, $backup);

    expect($stats['tasks'])->toBe(2)
        ->and(exportedBackup($bob)['tasks'])->toBe($backup['tasks'])
        ->and(Task::query()->pluck('title')->sort()->values()->all())->toBe(['Đi chợ', 'Đổ rác'])
        ->and(TaskStep::query()->sole()->user_id)->toBe($bob->id)
        ->and($backup['tasks'][0])->toMatchArray([
            'title' => 'Đổ rác', 'due_on' => '2026-11-19', 'due_time' => '07:00', 'important' => true,
            'recurrence' => 'weekly', 'repeat_weekdays' => 9, 'completed_at' => null,
            'steps' => [['title' => 'Rác tái chế', 'done' => true]],
        ]);
});

test('restoring an older file without tasks leaves the tasks alone', function () {
    $this->actingAs($user = User::factory()->create());
    $backup = exportedBackup($user);
    unset($backup['tasks']);
    Task::factory()->for($user)->create();

    app(RestoreBackup::class)->handle($user, $backup);

    expect(Task::query()->count())->toBe(1);
});

test('a backup with a broken task is rejected before anything is deleted', function (array $change, string $message) {
    $this->actingAs($user = User::factory()->create());
    Task::factory()->for($user)->due('2026-11-18')->create(['title' => 'Giữ nguyên']);
    $backup = exportedBackup($user);
    $backup['tasks'][0] = array_merge($backup['tasks'][0], $change);

    expect(fn () => app(RestoreBackup::class)->handle($user, $backup))->toThrow(HuiActionException::class, $message);
    expect(Task::query()->sole()->title)->toBe('Giữ nguyên');
})->with([
    'repeat without date' => [['due_on' => null, 'recurrence' => 'daily'], 'phải có ngày'],
    'time without date' => [['due_on' => null, 'due_time' => '08:00'], 'không có ngày'],
    'weekly without days' => [['recurrence' => 'weekly', 'repeat_weekdays' => 0], 'chưa chọn thứ'],
    'unknown repeat' => [['recurrence' => 'hourly'], ''],
]);

test('deleting the account deletes its tasks and steps', function () {
    $this->actingAs($user = User::factory()->create());
    TaskStep::factory()->for(Task::factory()->for($user)->create())->create();

    $user->delete();

    expect(Task::withoutGlobalScopes()->count())->toBe(0)->and(TaskStep::withoutGlobalScopes()->count())->toBe(0);
});
