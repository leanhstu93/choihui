<?php

declare(strict_types=1);

use App\Enums\TaskRepeat;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use App\Queries\TaskAgenda;
use App\Queries\TaskGroup;

/*
 * Nhóm việc trên các trang (document/10-viec-can-lam.md V3, V12, V13). 18/11/2026 là thứ 4.
 */

beforeEach(function () {
    $this->travelTo('2026-11-18 09:00:00');
    $this->actingAs($this->user = User::factory()->create());
    $this->today = now()->toImmutable();
});

/**
 * @param  list<TaskGroup>  $groups
 * @return array<string, list<string>>
 */
function titlesByGroup(array $groups): array
{
    $out = [];

    foreach ($groups as $group) {
        $out[$group->key.($group->key === TaskGroup::DAY || $group->key === TaskGroup::DONE_ON ? ' '.$group->date?->toDateString() : '')] = array_map(fn (Task $t) => $t->title, $group->tasks);
    }

    return $out;
}

test('the today page shows overdue, today and tomorrow in order with progress', function () {
    Task::factory()->for($this->user)->due('2026-11-16')->create(['title' => 'Trễ lâu']);
    Task::factory()->for($this->user)->due('2026-11-17')->important()->create(['title' => 'Trễ quan trọng']);
    Task::factory()->for($this->user)->due('2026-11-18')->create(['title' => 'Thường']);
    Task::factory()->for($this->user)->due('2026-11-18', '14:00')->create(['title' => 'Chiều']);
    Task::factory()->for($this->user)->due('2026-11-18', '08:00')->create(['title' => 'Sáng']);
    Task::factory()->for($this->user)->due('2026-11-18')->important()->create(['title' => 'Gấp']);
    Task::factory()->for($this->user)->due('2026-11-19')->create(['title' => 'Mai']);
    Task::factory()->for($this->user)->due('2026-11-20')->create(['title' => 'Mốt']);
    Task::factory()->for($this->user)->create(['title' => 'Không hạn']);
    Task::factory()->for($this->user)->due('2026-11-18')->done('2026-11-18 08:00:00')->create(['title' => 'Xong sáng nay']);
    Task::factory()->for($this->user)->due('2026-11-17')->done('2026-11-17 20:00:00')->create(['title' => 'Xong hôm qua']);

    $page = app(TaskAgenda::class)->today($this->user, $this->today);

    expect(titlesByGroup($page['groups']))->toBe([
        'overdue' => ['Trễ lâu', 'Trễ quan trọng'],
        'today' => ['Gấp', 'Sáng', 'Chiều', 'Thường'],
        'tomorrow' => ['Mai'],
    ])
        ->and($page['doneToday'])->toBe(1)
        ->and($page['totalToday'])->toBe(7)
        ->and($page['nextTask'])->toBeNull();
});

test('a free day points to the nearest upcoming task', function () {
    Task::factory()->for($this->user)->due('2026-11-25')->create(['title' => 'Tuần sau']);
    Task::factory()->for($this->user)->due('2026-11-21')->create(['title' => 'Thứ 7']);

    $page = app(TaskAgenda::class)->today($this->user, $this->today);

    expect($page['groups'])->toBe([])
        ->and($page['totalToday'])->toBe(0)
        ->and($page['nextTask']?->title)->toBe('Thứ 7');
});

test('the all page groups by day for a week, then later and no date', function () {
    Task::factory()->for($this->user)->due('2026-11-17')->create(['title' => 'Trễ']);
    Task::factory()->for($this->user)->due('2026-11-20')->create(['title' => 'Thứ 6']);
    Task::factory()->for($this->user)->due('2026-11-25')->create(['title' => 'Thứ 4 sau']);
    Task::factory()->for($this->user)->due('2026-11-26')->create(['title' => 'Xa']);
    Task::factory()->for($this->user)->due('2026-11-24')->create(['title' => 'Thứ 3 sau']);
    Task::factory()->for($this->user)->create(['title' => 'Lúc nào cũng được']);

    expect(titlesByGroup(app(TaskAgenda::class)->all($this->user, $this->today)))->toBe([
        'overdue' => ['Trễ'],
        'day 2026-11-20' => ['Thứ 6'],
        'day 2026-11-24' => ['Thứ 3 sau'],
        'day 2026-11-25' => ['Thứ 4 sau'],
        'later' => ['Xa'],
        'someday' => ['Lúc nào cũng được'],
    ]);
});

test('search looks in title, note and steps, ignoring accents and case', function () {
    Task::factory()->for($this->user)->create(['title' => 'Đóng tiền ĐIỆN']);
    Task::factory()->for($this->user)->create(['title' => 'Gọi thợ', 'note' => 'sửa máy giặt']);
    $withStep = Task::factory()->for($this->user)->create(['title' => 'Đi chợ']);
    TaskStep::factory()->for($withStep)->create(['title' => 'Mua trứng']);
    Task::factory()->for($this->user)->create(['title' => 'Không liên quan']);

    $find = fn (string $q) => array_merge(...array_values(titlesByGroup(app(TaskAgenda::class)->all($this->user, $this->today, $q)))) ?: [];

    expect($find('tien dien'))->toBe(['Đóng tiền ĐIỆN'])
        ->and($find('MÁY GIẶT'))->toBe(['Gọi thợ'])
        ->and($find('trứng'))->toBe(['Đi chợ'])
        ->and($find('100%'))->toBe([]);
});

test('filter chips narrow the all page', function () {
    Task::factory()->for($this->user)->due('2026-11-18')->important()->create(['title' => 'Quan trọng']);
    Task::factory()->for($this->user)->repeating(TaskRepeat::Daily, '2026-11-18')->create(['title' => 'Lặp']);
    Task::factory()->for($this->user)->create(['title' => 'Không hạn']);

    $only = fn (string $filter) => array_merge(...array_values(titlesByGroup(app(TaskAgenda::class)->all($this->user, $this->today, filters: [$filter]))));

    expect($only(TaskAgenda::FILTER_IMPORTANT))->toBe(['Quan trọng'])
        ->and($only(TaskAgenda::FILTER_RECURRING))->toBe(['Lặp'])
        ->and($only(TaskAgenda::FILTER_SOMEDAY))->toBe(['Không hạn']);
});

test('the done page groups by completion day, newest first', function () {
    Task::factory()->for($this->user)->done('2026-11-17 21:00:00')->create(['title' => 'Hôm qua']);
    Task::factory()->for($this->user)->done('2026-11-18 07:00:00')->create(['title' => 'Sáng nay']);
    Task::factory()->for($this->user)->done('2026-11-18 08:30:00')->create(['title' => 'Vừa xong']);
    Task::factory()->for($this->user)->create(['title' => 'Chưa xong']);

    expect(titlesByGroup(app(TaskAgenda::class)->done($this->user)))->toBe([
        'done_on 2026-11-18' => ['Vừa xong', 'Sáng nay'],
        'done_on 2026-11-17' => ['Hôm qua'],
    ]);
});

test('step counts come with each task', function () {
    $task = Task::factory()->for($this->user)->due('2026-11-18')->create();
    TaskStep::factory()->for($task)->create(['done' => true]);
    TaskStep::factory()->for($task)->create();

    $loaded = app(TaskAgenda::class)->today($this->user, $this->today)['groups'][0]->tasks[0];

    expect($loaded->steps_count)->toBe(2)->and($loaded->steps_done_count)->toBe(1);
});

test('lists and counts never include another account\'s tasks', function () {
    $this->actingAs(User::factory()->create());
    Task::factory()->due('2026-11-17')->create();
    Task::factory()->done()->create();
    $this->actingAs($this->user);
    Task::factory()->for($this->user)->due('2026-11-18')->create(['title' => 'Của tôi']);

    $agenda = app(TaskAgenda::class);

    expect($agenda->dueCount($this->user, $this->today))->toBe(1)
        ->and($agenda->done($this->user))->toBe([])
        ->and(titlesByGroup($agenda->all($this->user, $this->today)))->toBe(['today' => ['Của tôi']])
        ->and(array_map(fn (Task $t) => $t->title, $agenda->dueForEmail($this->user, $this->today)))->toBe(['Của tôi']);
});
