<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Danh sách việc theo nhóm (document/10-viec-can-lam.md V3, V12, V13, VL-03..VL-05, VL-11).
 * Lọc user_id tường minh và bỏ global scope, vì email buổi sáng chạy nền không có ai đăng nhập.
 */
final class TaskAgenda
{
    public const int WEEK_DAYS = 7;

    public const string FILTER_IMPORTANT = 'important';

    public const string FILTER_RECURRING = 'recurring';

    public const string FILTER_SOMEDAY = 'someday';

    /**
     * Trang Hôm nay: Quá hạn · Hôm nay · Ngày mai, kèm tiến độ trong ngày.
     *
     * @return array{groups: list<TaskGroup>, doneToday: int, totalToday: int, nextTask: Task|null}
     */
    public function today(User $user, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $tasks = $this->openQuery($user)->whereNotNull('due_on')->where('due_on', '<=', $today->addDay()->toDateString())->get()->all();

        $groups = $this->nonEmpty([
            new TaskGroup(TaskGroup::OVERDUE, $this->sorted(array_filter($tasks, fn (Task $t) => $t->due_on?->lt($today) === true), byDate: true)),
            new TaskGroup(TaskGroup::TODAY, $this->sorted(array_filter($tasks, fn (Task $t) => $t->due_on?->isSameDay($today) === true)), $today),
            new TaskGroup(TaskGroup::TOMORROW, $this->sorted(array_filter($tasks, fn (Task $t) => $t->due_on?->isSameDay($today->addDay()) === true)), $today->addDay()),
        ]);

        $openToday = count(array_filter($tasks, fn (Task $t) => $t->due_on?->lte($today) === true));
        $doneToday = $this->ownedQuery($user)->where('completed_at', '>=', $today)->where('completed_at', '<', $today->addDay())->count();

        return [
            'groups' => $groups,
            'doneToday' => $doneToday,
            'totalToday' => $doneToday + $openToday,
            'nextTask' => $openToday === 0 ? $this->nextAfter($user, $today) : null,
        ];
    }

    /**
     * Trang Tất cả: Quá hạn · Hôm nay · Ngày mai · từng ngày trong 7 ngày tới · Sau này · Chưa có hạn.
     *
     * @param  list<string>  $filters  FILTER_*
     * @return list<TaskGroup>
     */
    public function all(User $user, CarbonImmutable $today, ?string $search = null, array $filters = []): array
    {
        $today = $today->startOfDay();
        $query = $this->openQuery($user);
        $this->applySearch($query, $search);

        if (in_array(self::FILTER_IMPORTANT, $filters, true)) {
            $query->where('important', true);
        }

        if (in_array(self::FILTER_RECURRING, $filters, true)) {
            $query->where('recurrence', '!=', 'none');
        }

        if (in_array(self::FILTER_SOMEDAY, $filters, true)) {
            $query->whereNull('due_on');
        }

        $tasks = $query->get()->all();
        $dated = array_filter($tasks, fn (Task $t) => $t->due_on !== null);
        $weekEnd = $today->addDays(self::WEEK_DAYS);

        $groups = [
            new TaskGroup(TaskGroup::OVERDUE, $this->sorted(array_filter($dated, fn (Task $t) => $t->due_on?->lt($today) === true), byDate: true)),
            new TaskGroup(TaskGroup::TODAY, $this->sorted(array_filter($dated, fn (Task $t) => $t->due_on?->isSameDay($today) === true)), $today),
            new TaskGroup(TaskGroup::TOMORROW, $this->sorted(array_filter($dated, fn (Task $t) => $t->due_on?->isSameDay($today->addDay()) === true)), $today->addDay()),
        ];

        for ($day = $today->addDays(2); $day->lte($weekEnd); $day = $day->addDay()) {
            $groups[] = new TaskGroup(TaskGroup::DAY, $this->sorted(array_filter($dated, fn (Task $t) => $t->due_on?->isSameDay($day) === true)), $day);
        }

        $groups[] = new TaskGroup(TaskGroup::LATER, $this->sorted(array_filter($dated, fn (Task $t) => $t->due_on?->gt($weekEnd) === true), byDate: true));
        $groups[] = new TaskGroup(TaskGroup::SOMEDAY, $this->sorted(array_filter($tasks, fn (Task $t) => $t->due_on === null)));

        return $this->nonEmpty($groups);
    }

    /**
     * Trang Đã xong: theo ngày xong, mới nhất lên đầu.
     *
     * @return list<TaskGroup>
     */
    public function done(User $user, ?string $search = null): array
    {
        $query = $this->ownedQuery($user)->done()->withCount(['steps', 'steps as steps_done_count' => fn (Builder $q) => $q->where('done', true)]);
        $this->applySearch($query, $search);

        $groups = [];

        foreach ($query->orderByDesc('completed_at')->orderByDesc('id')->get() as $task) {
            /** @var CarbonImmutable $completedAt */
            $completedAt = $task->completed_at;
            $groups[$completedAt->toDateString()][] = $task;
        }

        return array_map(
            fn (string $date, array $tasks) => new TaskGroup(TaskGroup::DONE_ON, $tasks, CarbonImmutable::parse($date)),
            array_keys($groups),
            array_values($groups),
        );
    }

    /**
     * Số việc quá hạn và hôm nay, cho huy hiệu trên tab (VL-11).
     */
    public function dueCount(User $user, CarbonImmutable $today): int
    {
        return $this->ownedQuery($user)->open()->whereNotNull('due_on')->where('due_on', '<=', $today->toDateString())->count();
    }

    /**
     * Việc quá hạn và hôm nay cho email buổi sáng (V14), quá hạn lên đầu.
     *
     * @return list<Task>
     */
    public function dueForEmail(User $user, CarbonImmutable $today): array
    {
        $tasks = $this->ownedQuery($user)->open()->whereNotNull('due_on')->where('due_on', '<=', $today->toDateString())->get()->all();

        return $this->sorted($tasks, byDate: true);
    }

    /**
     * @return Builder<Task>
     */
    private function ownedQuery(User $user): Builder
    {
        return Task::query()->withoutGlobalScope(Task::OWNER_SCOPE)->where('tasks.user_id', $user->id);
    }

    /**
     * @return Builder<Task>
     */
    private function openQuery(User $user): Builder
    {
        return $this->ownedQuery($user)->open()->withCount(['steps', 'steps as steps_done_count' => fn (Builder $q) => $q->where('done', true)]);
    }

    private function nextAfter(User $user, CarbonImmutable $today): ?Task
    {
        return $this->ownedQuery($user)->open()->where('due_on', '>', $today->toDateString())->orderBy('due_on')->orderByDesc('important')->first();
    }

    /**
     * Tìm trong tên, ghi chú và các bước (V12). Collation đã bỏ qua hoa thường và dấu,
     * riêng "đ" được đổi thành "d" ở cả hai phía vì collation coi đó là chữ khác.
     *
     * @param  Builder<Task>  $query
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $like = '%'.addcslashes(str_replace(['đ', 'Đ'], 'd', $search), '%_\\').'%';
        $column = fn (string $name): string => "REPLACE(REPLACE({$name}, 'đ', 'd'), 'Đ', 'd') LIKE ?";

        $query->where(fn (Builder $q) => $q
            ->whereRaw($column('tasks.title'), [$like])
            ->orWhereRaw($column('tasks.note'), [$like])
            ->orWhereHas('steps', fn (Builder $steps) => $steps->withoutGlobalScopes()->whereRaw($column('task_steps.title'), [$like])));
    }

    /**
     * Quan trọng lên trước, rồi việc có giờ theo giờ, rồi theo lúc tạo (V3). $byDate xếp theo hạn trước.
     *
     * @param  array<array-key, Task>  $tasks
     * @return list<Task>
     */
    private function sorted(array $tasks, bool $byDate = false): array
    {
        usort($tasks, fn (Task $a, Task $b) => [
            $byDate ? $a->due_on?->toDateString() : '',
            ! $a->important,
            $a->due_time === null,
            $a->due_time,
            $a->created_at->getTimestamp(),
            $a->id,
        ] <=> [
            $byDate ? $b->due_on?->toDateString() : '',
            ! $b->important,
            $b->due_time === null,
            $b->due_time,
            $b->created_at->getTimestamp(),
            $b->id,
        ]);

        return $tasks;
    }

    /**
     * @param  list<TaskGroup>  $groups
     * @return list<TaskGroup>
     */
    private function nonEmpty(array $groups): array
    {
        return array_values(array_filter($groups, fn (TaskGroup $group) => $group->tasks !== []));
    }
}
