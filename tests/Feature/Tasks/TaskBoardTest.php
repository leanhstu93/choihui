<?php

declare(strict_types=1);

use App\Actions\Tasks\CompleteTask;
use App\Domain\TaskQuickParse;
use App\Enums\TaskRepeat;
use App\Livewire\Tasks\TaskBoard;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
 * Trang sổ việc cần làm (document/10-viec-can-lam.md VL-01..VL-08, VL-11). 18/11/2026 là thứ 4.
 */

/**
 * Livewire chuyển assertSeeInOrder sau set / call sang phản hồi JSON, nên kiểm tra thẳng trên HTML.
 *
 * @param  list<string>  $texts
 */
function htmlInOrder(Testable $component, array $texts): void
{
    $html = $component->html();
    $offset = 0;

    foreach ($texts as $text) {
        $found = mb_strpos($html, e($text), $offset);
        expect($found)->not->toBeFalse("Không thấy \"{$text}\" sau vị trí {$offset}");
        $offset = (int) $found + mb_strlen(e($text));
    }
}

beforeEach(function () {
    $this->travelTo('2026-11-18 09:00:00');
});

test('both roles get a tasks area with its own navigation and a due badge on the tab', function (User $user) {
    $this->actingAs($user);
    Task::factory()->for($user)->due('2026-11-17')->create();
    Task::factory()->for($user)->due('2026-11-18')->create();
    Task::factory()->for($user)->due('2026-11-19')->create();

    $this->get(route('tasks.index'))->assertOk()
        ->assertSeeInOrder(['Gia đình', 'Việc', '2 việc cần làm hôm nay', 'Chào buổi sáng', 'Thứ 4, 18/11/2026', '10/10 ÂL', 'Hôm nay', 'Tất cả', 'Đã xong', 'Cài đặt'], false);

    $this->get(route('tasks.all'))->assertOk()->assertSee('Tất cả việc');
    $this->get(route('tasks.done'))->assertOk()->assertSee('tự xóa');
})->with([
    'member' => fn () => User::factory()->create(),
    'host' => fn () => User::factory()->host()->create(),
]);

test('guests are sent to the login page', function () {
    $this->get(route('tasks.index'))->assertRedirect(route('login'));
});

test('a new account sees an introduction with one-tap suggestions', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(TaskBoard::class)
        ->assertSee('Ghi việc cần làm, không lo quên')
        ->call('addSuggestion', '🪔 Thắp hương rằm')
        ->assertDispatched('task-toast')
        ->assertDontSee('Ghi việc cần làm, không lo quên');

    expect(Task::query()->sole())
        ->title->toBe('Thắp hương')
        ->recurrence->toBe(TaskRepeat::MonthlyLunar)
        ->due_on->toDateString()->toBe('2026-11-23');
});

test('quick add shows what it understood and lets a chip be dismissed', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(TaskBoard::class)
        ->set('draft', 'mua 2/9 lá cờ')
        ->assertSee('Đã hiểu:')
        ->assertSee('Thứ 5, 02/09')
        ->call('dismissChip', TaskQuickParse::KIND_DATE)
        ->assertDontSee('Đã hiểu:')
        ->call('add')
        ->assertSet('draft', '')
        ->assertSet('ignored', []);

    // Trang Hôm nay: việc không có ngày được để hạn hôm nay.
    expect(Task::query()->sole())->title->toBe('mua 2/9 lá cờ')->due_on->toDateString()->toBe('2026-11-18');
    $component->assertSee('mua 2/9 lá cờ');
});

test('quick add previews lunar dates too', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(TaskBoard::class)->set('draft', 'Mua quà rằm tháng 11')->assertSee('Thứ 4, 23/12');
});

test('quick add on the all page keeps a task without a date', function () {
    $this->actingAs(User::factory()->create());

    Livewire::withQueryParams([])->test(TaskBoard::class)->set('view', TaskBoard::VIEW_ALL)
        ->set('draft', 'Đọc sách')->call('add')
        ->assertSee('Chưa có hạn');

    expect(Task::query()->sole()->due_on)->toBeNull();
});

test('quick add without a title explains what to type', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(TaskBoard::class)->set('draft', 'mai 8h')->call('add')
        ->assertSet('actionError', 'Gõ tên việc trước, ví dụ "Đóng tiền điện mai".');

    expect(Task::query()->count())->toBe(0);
});

test('completing returns a message with the next occurrence, and undo puts it back', function () {
    $this->actingAs($user = User::factory()->create());
    $task = Task::factory()->for($user)->repeating(TaskRepeat::Daily, '2026-11-18')->create(['title' => 'Tưới cây']);

    $component = Livewire::test(TaskBoard::class);
    $result = $component->instance()->complete($task->id, app(CompleteTask::class));

    expect($result['message'])->toBe('Đã xong "Tưới cây" · lần tới: Mai')
        ->and(Task::query()->open()->sole()->due_on->toDateString())->toBe('2026-11-19');

    $component->call('undo', $task->id);

    expect(Task::query()->sole()->completed_at)->toBeNull();
});

test('the today page celebrates when everything is done and points ahead when free', function () {
    $this->actingAs($user = User::factory()->create());
    Task::factory()->for($user)->due('2026-11-18')->done('2026-11-18 08:00:00')->create();
    Task::factory()->for($user)->due('2026-11-21')->create(['title' => 'Dọn kho']);

    Livewire::test(TaskBoard::class)->assertSee('Xong hết việc hôm nay!')->assertSee('1/1');

    Task::query()->done()->delete();

    Livewire::test(TaskBoard::class)->assertSee('Hôm nay rảnh')->assertSee('Dọn kho')->assertSee('Thứ 7, 21/11');
});

test('two or more overdue tasks offer moving them all to today', function () {
    $this->actingAs($user = User::factory()->create());
    Task::factory()->count(2)->for($user)->due('2026-11-16')->create();

    Livewire::test(TaskBoard::class)
        ->assertSee('2 việc quá hạn')
        ->assertSee('Trễ 2 ngày')
        ->call('rescheduleOverdue')
        ->assertDontSee('việc quá hạn');

    expect(Task::query()->where('due_on', '2026-11-18')->count())->toBe(2);
});

test('the reschedule menu moves a task', function (string $option, string $date, ?string $time) {
    $this->actingAs($user = User::factory()->create());
    $task = Task::factory()->for($user)->due('2026-11-18', '08:00')->create();

    Livewire::test(TaskBoard::class)->call('reschedule', $task->id, $option)->assertDispatched('task-toast');

    expect($task->fresh())->due_on->toDateString()->toBe($date)->due_time->toBe($time);
})->with([
    'this afternoon' => ['this_afternoon', '2026-11-18', '14:00'],
    'tomorrow keeps the time' => ['tomorrow', '2026-11-19', '08:00'],
    'weekend' => ['weekend', '2026-11-21', '08:00'],
    'next week' => ['next_week', '2026-11-23', '08:00'],
    'picked date' => ['2026-12-01', '2026-12-01', '08:00'],
]);

test('the editor saves the form, shows the next occurrences and validates', function () {
    $this->actingAs($user = User::factory()->create());
    $task = Task::factory()->for($user)->due('2026-11-18')->create(['title' => 'Họp']);

    Livewire::test(TaskBoard::class)
        ->call('open', $task->id)
        ->assertSee('Chi tiết việc')
        ->set('title', 'Họp tổ dân phố')
        ->call('setRecurrence', 'weekly')
        ->assertSet('weekdays', [3])
        ->call('toggleWeekday', 6)
        ->tap(fn ($c) => htmlInOrder($c, ['Các lần tới', 'Thứ 7, 21/11/2026', 'Thứ 4, 25/11/2026', 'Thứ 7, 28/11/2026']))
        ->set('dueTime', '19:30')
        ->set('important', true)
        ->set('note', 'Nhà văn hóa')
        ->call('save')
        ->assertSet('editingId', null)
        ->assertHasNoErrors();

    expect($task->fresh())
        ->title->toBe('Họp tổ dân phố')
        ->repeat_weekdays->toBe(0b0100100)
        ->due_time->toBe('19:30')
        ->important->toBeTrue()
        ->note->toBe('Nhà văn hóa');

    Livewire::test(TaskBoard::class)->call('open', $task->id)
        ->set('title', '')->call('toggleWeekday', 3)->call('toggleWeekday', 6)->call('save')
        ->assertHasErrors(['title', 'repeat_weekdays']);
});

test('removing the date in the editor also stops the repeat', function () {
    $this->actingAs($user = User::factory()->create());
    $task = Task::factory()->for($user)->repeating(TaskRepeat::Daily, '2026-11-18')->create(['due_time' => '06:00']);

    Livewire::test(TaskBoard::class)->call('open', $task->id)->call('setDue', 'none')->call('save')->assertHasNoErrors();

    expect($task->fresh())->due_on->toBeNull()->due_time->toBeNull()->recurrence->toBe(TaskRepeat::None);
});

test('steps can be added, ticked, renamed and deleted from the editor', function () {
    $this->actingAs($user = User::factory()->create());
    $task = Task::factory()->for($user)->due('2026-11-18')->create();

    $component = Livewire::test(TaskBoard::class)->call('open', $task->id)
        ->set('newStep', 'Chụp hóa đơn')->call('addStep')
        ->assertSet('newStep', '')
        ->assertSee('Chụp hóa đơn');

    $step = TaskStep::query()->sole();

    $component->call('toggleStep', $step->id, true)->assertDispatched('task-steps-done')
        ->call('renameStep', $step->id, 'Chụp hóa đơn điện')
        ->assertSee('☑ 1/1');
    expect($step->fresh())->done->toBeTrue()->title->toBe('Chụp hóa đơn điện');

    $component->call('deleteStep', $step->id);
    expect(TaskStep::query()->count())->toBe(0);
});

test('deleting a repeating task from the editor can keep the repeat', function () {
    $this->actingAs($user = User::factory()->create());
    $task = Task::factory()->for($user)->repeating(TaskRepeat::Weekly, '2026-11-18', 0b0000100)->create();

    Livewire::test(TaskBoard::class)->call('open', $task->id)
        ->assertSee('Chỉ xóa lần này')
        ->call('delete', true)
        ->assertSet('editingId', null);

    expect(Task::query()->sole()->due_on->toDateString())->toBe('2026-11-25');
});

test('the done page lists finished tasks by day and can reopen one', function () {
    $this->actingAs($user = User::factory()->create());
    $task = Task::factory()->for($user)->done('2026-11-17 20:00:00')->create(['title' => 'Đi chợ']);

    Livewire::test(TaskBoard::class)->set('view', TaskBoard::VIEW_DONE)
        ->tap(fn ($c) => htmlInOrder($c, ['Hôm qua', 'Đi chợ', 'Xong lúc 20:00', 'Làm lại']))
        ->call('reopen', $task->id)
        ->assertSee('Chưa có việc nào xong');

    expect($task->fresh()->completed_at)->toBeNull();
});

test('search and filter chips on the all page', function () {
    $this->actingAs($user = User::factory()->create());
    Task::factory()->for($user)->important()->create(['title' => 'Đóng học phí']);
    Task::factory()->for($user)->create(['title' => 'Mua sữa']);

    Livewire::test(TaskBoard::class)->set('view', TaskBoard::VIEW_ALL)
        ->set('search', 'hoc phi')->assertSee('Đóng học phí')->assertDontSee('Mua sữa')
        ->set('search', 'không có')->assertSee('Không có việc nào khớp')
        ->set('search', '')->call('toggleFilter', 'important')->assertSee('Đóng học phí')->assertDontSee('Mua sữa')
        ->call('toggleFilter', 'nonsense')->assertSet('filters', ['important']);
});

test('one account cannot touch another account\'s tasks through the page', function (string $method, array $args) {
    $this->actingAs(User::factory()->create());
    $task = Task::factory()->due('2026-11-18')->create(['title' => 'Bí mật']);
    $step = TaskStep::factory()->for($task)->create();

    $this->actingAs(User::factory()->create());

    $component = Livewire::test(TaskBoard::class)->assertDontSee('Bí mật');

    expect(fn () => $component->call($method, ...array_map(fn ($arg) => match ($arg) {
        'TASK' => $task->id, 'STEP' => $step->id, default => $arg
    }, $args)))
        ->toThrow(ModelNotFoundException::class);

    expect(Task::withoutGlobalScopes()->find($task->id))->title->toBe('Bí mật')->completed_at->toBeNull();
})->with([
    'complete' => ['complete', ['TASK']],
    'open' => ['open', ['TASK']],
    'reschedule' => ['reschedule', ['TASK', 'tomorrow']],
    'reopen' => ['reopen', ['TASK']],
    'toggle a step' => ['toggleStep', ['STEP', true]],
]);
