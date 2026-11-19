<?php

declare(strict_types=1);

use App\Actions\Tasks\CompleteTask;
use App\Actions\Tasks\DeleteTask;
use App\Actions\Tasks\QuickAddTask;
use App\Actions\Tasks\ReopenTask;
use App\Actions\Tasks\RescheduleOverdueTasks;
use App\Actions\Tasks\RescheduleTask;
use App\Actions\Tasks\SaveTask;
use App\Actions\Tasks\SaveTaskStep;
use App\Actions\Tasks\ToggleTaskStep;
use App\Domain\TaskQuickParse;
use App\Enums\TaskRepeat;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StoreTaskRequest;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Tạo dữ liệu dưới tên một tài khoản khác (OwnedByUser gán chủ theo người đang đăng nhập), rồi quay lại.
 */
function strangerTask(Closure $make): Task
{
    $me = auth()->user();
    test()->actingAs(User::factory()->create());
    $task = $make();
    test()->actingAs($me);

    return $task;
}

/*
 * Thao tác trên việc (document/10-viec-can-lam.md V4–V9, V15). 18/11/2026 là thứ 4.
 */

beforeEach(function () {
    $this->travelTo('2026-11-18 09:00:00');
    $this->actingAs($this->user = User::factory()->create());
});

test('quick add saves what the sentence says', function () {
    $task = app(QuickAddTask::class)->handle('Đóng tiền điện mai 8h !');

    expect($task->fresh())
        ->title->toBe('Đóng tiền điện')
        ->due_on->toDateString()->toBe('2026-11-19')
        ->due_time->toBe('08:00')
        ->important->toBeTrue()
        ->user_id->toBe($this->user->id);
});

test('quick add keeps a dismissed chip in the title', function () {
    $task = app(QuickAddTask::class)->handle('mua 2/9 lá cờ', [TaskQuickParse::KIND_DATE]);

    expect($task->title)->toBe('mua 2/9 lá cờ')->and($task->due_on)->toBeNull();
});

test('quick add needs a title', function () {
    app(QuickAddTask::class)->handle('mai 8h !');
})->throws(HuiActionException::class, 'Gõ tên việc trước');

test('saving fills the repeat anchors from the due date and drops what does not apply', function () {
    $weekly = app(SaveTask::class)->handle(null, ['title' => 'Họp', 'due_on' => '2026-11-18', 'recurrence' => 'weekly']);
    $lunar = app(SaveTask::class)->handle(null, ['title' => 'Thắp hương', 'due_on' => '2026-11-23', 'recurrence' => 'monthly_lunar', 'repeat_weekdays' => 5]);
    $loose = app(SaveTask::class)->handle(null, ['title' => 'Đọc sách', 'due_on' => null, 'due_time' => '08:00', 'recurrence' => 'daily', 'note' => '  ']);

    expect($weekly->repeat_weekdays)->toBe(0b0000100)
        ->and($lunar->repeat_day)->toBe(15)
        ->and($lunar->repeat_weekdays)->toBe(0)
        ->and($loose->fresh())->due_time->toBeNull()->recurrence->toBe(TaskRepeat::None)->note->toBeNull();
});

test('the form refuses a repeat without a date, a time without a date and a week without days', function (array $data, string $field) {
    $validator = StoreTaskRequest::makeValidator(array_merge(['title' => 'Việc', 'recurrence' => 'none', 'repeat_weekdays' => 0], $data));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->keys())->toContain($field);
})->with([
    'repeat without a date' => [['recurrence' => 'daily'], 'due_on'],
    'time without a date' => [['due_time' => '08:00'], 'due_time'],
    'weekly without days' => [['recurrence' => 'weekly', 'due_on' => '2026-11-18'], 'repeat_weekdays'],
    'lunar day 31' => [['recurrence' => 'monthly_lunar', 'due_on' => '2026-11-18', 'repeat_day' => 31], 'repeat_day'],
]);

test('completing a repeating task creates the next one with fresh steps', function () {
    $task = Task::factory()->for($this->user)->repeating(TaskRepeat::Weekly, '2026-11-16', 0b0001001)->important()->create(['due_time' => '07:00', 'note' => 'Thùng xanh']);
    TaskStep::factory()->for($task)->create(['title' => 'Rác tái chế', 'done' => true, 'position' => 1]);

    $next = app(CompleteTask::class)->handle($task);

    expect($task->fresh())->completed_at->toDateTimeString()->toBe('2026-11-18 09:00:00')->spawned_task_id->toBe($next?->id)
        ->and($next?->fresh())
        ->due_on->toDateString()->toBe('2026-11-19')
        ->due_time->toBe('07:00')
        ->important->toBeTrue()
        ->note->toBe('Thùng xanh')
        ->completed_at->toBeNull()
        ->and($next?->steps->map->only(['title', 'done', 'user_id'])->all())->toBe([['title' => 'Rác tái chế', 'done' => false, 'user_id' => $this->user->id]]);
});

test('completing twice or completing a one-off task makes nothing new', function () {
    $repeating = Task::factory()->for($this->user)->repeating(TaskRepeat::Daily, '2026-11-18')->create();
    $oneOff = Task::factory()->for($this->user)->due('2026-11-18')->create();

    app(CompleteTask::class)->handle($repeating);
    expect(app(CompleteTask::class)->handle($repeating->fresh()))->toBeNull()
        ->and(app(CompleteTask::class)->handle($oneOff))->toBeNull()
        ->and(Task::query()->count())->toBe(3);
});

test('undo removes the next occurrence only while it is untouched', function () {
    $task = Task::factory()->for($this->user)->repeating(TaskRepeat::Daily, '2026-11-18')->create();
    app(CompleteTask::class)->handle($task);

    app(ReopenTask::class)->handle($task->fresh());

    expect(Task::query()->count())->toBe(1)
        ->and($task->fresh())->completed_at->toBeNull()->spawned_task_id->toBeNull();

    $next = app(CompleteTask::class)->handle($task->fresh());
    $this->travel(1)->minutes();
    app(SaveTask::class)->handle($next, ['title' => 'Đã sửa', 'due_on' => '2026-11-19', 'recurrence' => 'daily']);
    app(ReopenTask::class)->handle($task->fresh());

    expect(Task::query()->count())->toBe(2);
});

test('rescheduling keeps the time unless a new one is given', function () {
    $task = Task::factory()->for($this->user)->due('2026-11-17', '08:00')->create();

    app(RescheduleTask::class)->handle($task, CarbonImmutable::parse('2026-11-21'));
    expect($task->fresh())->due_on->toDateString()->toBe('2026-11-21')->due_time->toBe('08:00');

    app(RescheduleTask::class)->handle($task, CarbonImmutable::parse('2026-11-18'), '15:00');
    expect($task->fresh()->due_time)->toBe('15:00');

    app(RescheduleTask::class)->handle($task, null);
    expect($task->fresh())->due_on->toBeNull()->due_time->toBeNull();
});

test('a repeating task cannot lose its date', function () {
    $task = Task::factory()->for($this->user)->repeating(TaskRepeat::Daily, '2026-11-18')->create();

    app(RescheduleTask::class)->handle($task, null);
})->throws(HuiActionException::class, 'luôn cần có ngày');

test('moving overdue tasks to today touches only open overdue tasks of this account', function () {
    Task::factory()->for($this->user)->due('2026-11-10')->create();
    Task::factory()->for($this->user)->due('2026-11-17', '20:00')->create();
    Task::factory()->for($this->user)->due('2026-11-17')->done()->create();
    Task::factory()->for($this->user)->due('2026-11-20')->create();
    $stranger = strangerTask(fn () => Task::factory()->due('2026-11-10')->create());

    expect(app(RescheduleOverdueTasks::class)->handle())->toBe(2)
        ->and(Task::query()->open()->pluck('due_on')->map->toDateString()->sort()->values()->all())->toBe(['2026-11-18', '2026-11-18', '2026-11-20'])
        ->and(Task::withoutGlobalScopes()->find($stranger->id)?->due_on?->toDateString())->toBe('2026-11-10');
});

test('deleting only this occurrence keeps the repeat going', function () {
    $task = Task::factory()->for($this->user)->repeating(TaskRepeat::MonthlyLunar, '2026-11-23', day: 15)->create();

    app(DeleteTask::class)->handle($task, keepRepeating: true);

    expect(Task::query()->sole())->due_on->toDateString()->toBe('2026-12-23')->completed_at->toBeNull();
});

test('deleting and stopping the repeat leaves nothing', function () {
    $task = Task::factory()->for($this->user)->repeating(TaskRepeat::Daily, '2026-11-18')->create();
    TaskStep::factory()->for($task)->create();

    app(DeleteTask::class)->handle($task);

    expect(Task::query()->count())->toBe(0)->and(TaskStep::query()->count())->toBe(0);
});

test('a task holds at most 20 steps', function () {
    $task = Task::factory()->for($this->user)->create();
    TaskStep::factory()->count(Task::MAX_STEPS)->for($task)->create();

    app(SaveTaskStep::class)->handle($task, null, 'Bước 21');
})->throws(HuiActionException::class, 'tối đa 20 bước');

test('steps are added at the end and report when all are done', function () {
    $task = Task::factory()->for($this->user)->create();
    $first = app(SaveTaskStep::class)->handle($task, null, '  Chụp hóa đơn ');
    $second = app(SaveTaskStep::class)->handle($task, null, 'Chuyển khoản');

    expect($first->title)->toBe('Chụp hóa đơn')
        ->and($second->position)->toBeGreaterThan($first->position)
        ->and(app(ToggleTaskStep::class)->handle($first, true))->toBeFalse()
        ->and(app(ToggleTaskStep::class)->handle($second, true))->toBeTrue();
});
