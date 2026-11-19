<?php

declare(strict_types=1);

namespace App\Livewire\Tasks;

use App\Actions\Tasks\CompleteTask;
use App\Actions\Tasks\DeleteTask;
use App\Actions\Tasks\DeleteTaskStep;
use App\Actions\Tasks\QuickAddTask;
use App\Actions\Tasks\ReopenTask;
use App\Actions\Tasks\RescheduleOverdueTasks;
use App\Actions\Tasks\RescheduleTask;
use App\Actions\Tasks\SaveTask;
use App\Actions\Tasks\SaveTaskStep;
use App\Actions\Tasks\ToggleTaskStep;
use App\Domain\LunarCalendar;
use App\Domain\TaskQuickParse;
use App\Domain\TaskRecurrence;
use App\Enums\TaskRepeat;
use App\Exceptions\HuiActionException;
use App\Http\Requests\StoreTaskRequest;
use App\Models\Task;
use App\Models\TaskStep;
use App\Models\User;
use App\Queries\TaskAgenda;
use App\Queries\TaskGroup;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Sổ việc cần làm: Hôm nay, Tất cả, Đã xong và khung chi tiết (document/10-viec-can-lam.md VL-01..VL-08).
 */
#[Layout('layouts.app')]
#[Title('Việc cần làm')]
class TaskBoard extends Component
{
    public const string VIEW_TODAY = 'today';

    public const string VIEW_ALL = 'all';

    public const string VIEW_DONE = 'done';

    /**
     * Gợi ý bấm là thêm khi sổ còn trống (VL-08): nhãn => câu thêm nhanh.
     */
    public const array SUGGESTIONS = [
        '💡 Đóng tiền điện' => 'Đóng tiền điện hằng tháng',
        '🪔 Thắp hương rằm' => 'Thắp hương rằm hằng tháng',
        '🪔 Thắp hương mùng 1' => 'Thắp hương mùng 1 hằng tháng',
        '🗑️ Đổ rác' => 'Đổ rác mỗi thứ 2 và thứ 5',
        '🛵 Gia hạn bảo hiểm xe' => 'Gia hạn bảo hiểm xe mỗi năm',
    ];

    public string $view = self::VIEW_TODAY;

    /** Dòng thêm nhanh và các loại chip người dùng đã bấm bỏ. */
    public string $draft = '';

    /** @var list<string> */
    public array $ignored = [];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** @var list<string> */
    public array $filters = [];

    /** Khung chi tiết. */
    public ?string $editingId = null;

    public string $title = '';

    public string $note = '';

    public string $dueOn = '';

    public string $dueTime = '';

    public bool $important = false;

    public string $recurrence = 'none';

    /** @var list<int> Thứ ISO 1–7 */
    public array $weekdays = [];

    public string $newStep = '';

    public ?string $actionError = null;

    public function mount(): void
    {
        $this->view = match (true) {
            request()->routeIs('tasks.all') => self::VIEW_ALL,
            request()->routeIs('tasks.done') => self::VIEW_DONE,
            default => self::VIEW_TODAY,
        };
    }

    public function updatedDraft(): void
    {
        if (trim($this->draft) === '') {
            $this->ignored = [];
        }
    }

    public function dismissChip(string $kind): void
    {
        if (in_array($kind, [TaskQuickParse::KIND_DATE, TaskQuickParse::KIND_TIME, TaskQuickParse::KIND_IMPORTANT, TaskQuickParse::KIND_REPEAT], true)) {
            $this->ignored[] = $kind;
        }
    }

    /**
     * Thêm nhanh (VL-01). Trang Hôm nay mà không có ngày thì để hạn hôm nay, như ghi giấy dán.
     */
    public function add(QuickAddTask $quickAdd): void
    {
        $this->actionError = null;

        if (trim($this->draft) === '') {
            return;
        }

        try {
            $task = $quickAdd->handle($this->draft, $this->ignored);
        } catch (HuiActionException $e) {
            $this->actionError = $e->getMessage();

            return;
        }

        if ($this->view === self::VIEW_TODAY && $task->due_on === null) {
            $task->update(['due_on' => now()->toDateString()]);
        }

        $this->reset('draft', 'ignored');
        $this->dispatch('tasks-changed');
        $this->dispatch('task-toast', message: 'Đã thêm "'.$task->title.'"'.(($badge = $task->dueBadge($this->today())) ? ' · '.$badge['label'] : ''));
    }

    public function addSuggestion(string $label, QuickAddTask $quickAdd): void
    {
        if (isset(self::SUGGESTIONS[$label])) {
            $quickAdd->handle(self::SUGGESTIONS[$label]);
            $this->dispatch('tasks-changed');
            $this->dispatch('task-toast', message: 'Đã thêm "'.self::SUGGESTIONS[$label].'"');
        }
    }

    /**
     * Đánh dấu xong (V8). Không vẽ lại ngay để dòng kịp mờ đi; giao diện tự gọi $refresh sau đó.
     *
     * @return array{message: string}
     */
    #[Renderless]
    public function complete(string $id, CompleteTask $completeTask): array
    {
        $task = $this->findTask($id);
        $next = $completeTask->handle($task);

        if ($this->editingId === $id) {
            $this->editingId = null;
        }

        $this->dispatch('tasks-changed');
        $message = 'Đã xong "'.$task->title.'"';

        if ($next !== null && ($badge = $next->dueBadge($this->today()))) {
            $message .= ' · lần tới: '.$badge['label'];
        }

        return ['message' => $message];
    }

    public function undo(string $id, ReopenTask $reopenTask): void
    {
        $reopenTask->handle($this->findTask($id));
        $this->dispatch('tasks-changed');
    }

    public function reopen(string $id, ReopenTask $reopenTask): void
    {
        $task = $this->findTask($id);
        $reopenTask->handle($task);
        $this->dispatch('tasks-changed');
        $this->dispatch('task-toast', message: 'Đã đưa "'.$task->title.'" về chưa xong');
    }

    /**
     * Dời (V9): this_afternoon, tomorrow, weekend, next_week, none, hoặc một ngày Y-m-d.
     */
    public function reschedule(string $id, string $option, RescheduleTask $rescheduleTask): void
    {
        $this->actionError = null;
        $task = $this->findTask($id);
        $today = $this->today();

        [$date, $time] = match ($option) {
            'this_afternoon' => [$today, '14:00'],
            'tomorrow' => [$today->addDay(), RescheduleTask::KEEP_TIME],
            'weekend' => [$today->dayOfWeekIso >= 6 ? $today : $today->next(CarbonImmutable::SATURDAY), RescheduleTask::KEEP_TIME],
            'next_week' => [$today->next(CarbonImmutable::MONDAY), RescheduleTask::KEEP_TIME],
            'none' => [null, null],
            default => [preg_match('/^\d{4}-\d{2}-\d{2}$/', $option) === 1 ? CarbonImmutable::parse($option) : $today, RescheduleTask::KEEP_TIME],
        };

        try {
            $rescheduleTask->handle($task, $date, $time);
        } catch (HuiActionException $e) {
            $this->actionError = $e->getMessage();

            return;
        }

        $badge = $task->fresh()?->dueBadge($today);
        $this->dispatch('tasks-changed');
        $this->dispatch('task-toast', message: 'Đã dời "'.$task->title.'"'.($badge ? ' sang '.$badge['label'] : ' về Chưa có hạn'));
    }

    public function rescheduleOverdue(RescheduleOverdueTasks $rescheduleOverdue): void
    {
        $count = $rescheduleOverdue->handle();
        $this->dispatch('tasks-changed');
        $this->dispatch('task-toast', message: "Đã dời {$count} việc quá hạn sang hôm nay");
    }

    public function toggleFilter(string $filter): void
    {
        if (! in_array($filter, [TaskAgenda::FILTER_IMPORTANT, TaskAgenda::FILTER_RECURRING, TaskAgenda::FILTER_SOMEDAY], true)) {
            return;
        }

        $this->filters = in_array($filter, $this->filters, true)
            ? array_values(array_diff($this->filters, [$filter]))
            : [...$this->filters, $filter];
    }

    /**
     * Mở khung chi tiết (VL-02).
     */
    public function open(string $id): void
    {
        $task = $this->findTask($id);

        $this->resetValidation();
        $this->actionError = null;
        $this->editingId = $task->id;
        $this->title = $task->title;
        $this->note = (string) $task->note;
        $this->dueOn = (string) $task->due_on?->toDateString();
        $this->dueTime = (string) $task->due_time;
        $this->important = $task->important;
        $this->recurrence = $task->recurrence->value;
        $this->weekdays = [];

        for ($iso = 1; $iso <= 7; $iso++) {
            if (($task->repeat_weekdays & (1 << ($iso - 1))) !== 0) {
                $this->weekdays[] = $iso;
            }
        }

        $this->newStep = '';
    }

    public function close(): void
    {
        $this->editingId = null;
        $this->resetValidation();
    }

    public function setDue(string $option): void
    {
        $today = $this->today();

        $this->dueOn = match ($option) {
            'today' => $today->toDateString(),
            'tomorrow' => $today->addDay()->toDateString(),
            'weekend' => ($today->dayOfWeekIso >= 6 ? $today : $today->next(CarbonImmutable::SATURDAY))->toDateString(),
            'next_week' => $today->next(CarbonImmutable::MONDAY)->toDateString(),
            default => '',
        };

        if ($this->dueOn === '') {
            $this->dueTime = '';
            $this->recurrence = TaskRepeat::None->value;
        }
    }

    public function setRecurrence(string $value): void
    {
        $repeat = TaskRepeat::tryFrom($value) ?? TaskRepeat::None;
        $this->recurrence = $repeat->value;

        if ($repeat !== TaskRepeat::None && $this->dueOn === '') {
            $this->dueOn = $this->today()->toDateString();
        }

        if ($repeat === TaskRepeat::Weekly && $this->weekdays === [] && $this->dueOn !== '') {
            $this->weekdays = [CarbonImmutable::parse($this->dueOn)->dayOfWeekIso];
        }
    }

    public function toggleWeekday(int $iso): void
    {
        if ($iso < 1 || $iso > 7) {
            return;
        }

        $this->weekdays = in_array($iso, $this->weekdays, true)
            ? array_values(array_diff($this->weekdays, [$iso]))
            : [...$this->weekdays, $iso];
        sort($this->weekdays);
    }

    public function save(SaveTask $saveTask): void
    {
        $task = $this->editingTask();
        $data = $this->formData();

        StoreTaskRequest::makeValidator($data)->validate();

        $saveTask->handle($task, $data + ['repeat_day' => $this->anchorDayFor($task, $data)]);
        $this->editingId = null;
        $this->dispatch('tasks-changed');
        $this->dispatch('task-toast', message: 'Đã lưu "'.trim($this->title).'"');
    }

    public function delete(bool $keepRepeating, DeleteTask $deleteTask): void
    {
        $task = $this->editingTask();
        Gate::authorize('delete', $task);

        $deleteTask->handle($task, $keepRepeating);
        $this->editingId = null;
        $this->dispatch('tasks-changed');
        $this->dispatch('task-toast', message: $keepRepeating && $task->isRecurring() ? 'Đã bỏ lần này, lịch lặp vẫn giữ' : 'Đã xóa "'.$task->title.'"');
    }

    public function addStep(SaveTaskStep $saveStep): void
    {
        $this->actionError = null;

        try {
            $saveStep->handle($this->editingTask(), null, $this->newStep);
            $this->newStep = '';
        } catch (HuiActionException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function toggleStep(string $stepId, bool $done, ToggleTaskStep $toggleStep): void
    {
        $allDone = $toggleStep->handle($this->findStep($stepId), $done);

        if ($allDone && $done) {
            $this->dispatch('task-steps-done');
        }
    }

    public function renameStep(string $stepId, string $title, SaveTaskStep $saveStep): void
    {
        $step = $this->findStep($stepId);

        try {
            $saveStep->handle($step->task, $step, $title);
        } catch (HuiActionException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function deleteStep(string $stepId, DeleteTaskStep $deleteStep): void
    {
        $deleteStep->handle($this->findStep($stepId));
    }

    public function render(TaskAgenda $agenda): View
    {
        /** @var User $user */
        $user = Auth::user();
        $today = $this->today();
        $hasAny = Task::query()->exists();

        $page = match ($this->view) {
            self::VIEW_TODAY => $agenda->today($user, $today),
            self::VIEW_ALL => ['groups' => $agenda->all($user, $today, $this->search, $this->filters)],
            default => ['groups' => $agenda->done($user, $this->search)],
        };

        $editing = $this->editingId === null ? null : Task::query()->with('steps')->find($this->editingId);

        return view('livewire.tasks.task-board', [
            'today' => $today,
            'todayLunar' => LunarCalendar::fromSolar($today)->label(),
            'hasAny' => $hasAny,
            'page' => $page,
            'parsed' => trim($this->draft) === '' ? null : TaskQuickParse::parse($this->draft, now()->toImmutable(), $this->ignored),
            'editing' => $editing,
            'preview' => $this->preview(),
            'overdueCount' => count(collect($page['groups'])->firstWhere('key', TaskGroup::OVERDUE)->tasks ?? []),
        ]);
    }

    /**
     * 3 lần tới trong form (VL-02).
     *
     * @return list<CarbonImmutable>
     */
    private function preview(): array
    {
        $repeat = TaskRepeat::tryFrom($this->recurrence) ?? TaskRepeat::None;

        if ($this->editingId === null || $repeat === TaskRepeat::None || preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->dueOn) !== 1) {
            return [];
        }

        if ($repeat === TaskRepeat::Weekly && $this->weekdays === []) {
            return [];
        }

        $task = Task::query()->find($this->editingId);
        $data = $this->formData();

        return array_map(
            fn ($date) => CarbonImmutable::instance($date),
            TaskRecurrence::preview($repeat, new DateTimeImmutable($this->dueOn), 3, (int) $data['repeat_weekdays'], $this->anchorDayFor($task, $data)),
        );
    }

    /**
     * Ngày neo giữ nguyên khi chỉ sửa tên; đổi ngày hoặc đổi kiểu lặp thì lấy theo hạn mới.
     *
     * @param  array<string, mixed>  $data
     */
    private function anchorDayFor(?Task $task, array $data): ?int
    {
        if ($task === null || $task->repeat_day === null) {
            return null;
        }

        $sameRule = $task->recurrence->value === $data['recurrence'] && $task->due_on?->toDateString() === $data['due_on'];

        return $sameRule ? $task->repeat_day : null;
    }

    /**
     * @return array{title: string, note: string, due_on: string|null, due_time: string|null, important: bool, recurrence: string, repeat_weekdays: int}
     */
    private function formData(): array
    {
        $mask = 0;

        foreach ($this->weekdays as $iso) {
            $mask |= 1 << ($iso - 1);
        }

        return [
            'title' => trim($this->title),
            'note' => $this->note,
            'due_on' => $this->dueOn === '' ? null : $this->dueOn,
            'due_time' => $this->dueOn === '' || $this->dueTime === '' ? null : substr($this->dueTime, 0, 5),
            'important' => $this->important,
            'recurrence' => $this->recurrence,
            'repeat_weekdays' => $this->recurrence === TaskRepeat::Weekly->value ? $mask : 0,
        ];
    }

    private function editingTask(): Task
    {
        abort_if($this->editingId === null, 404);

        return $this->findTask($this->editingId);
    }

    private function findTask(string $id): Task
    {
        $task = Task::query()->findOrFail($id);
        Gate::authorize('update', $task);

        return $task;
    }

    private function findStep(string $id): TaskStep
    {
        $step = TaskStep::query()->findOrFail($id);
        Gate::authorize('update', $step->task);

        return $step;
    }

    private function today(): CarbonImmutable
    {
        return now()->toImmutable()->startOfDay();
    }
}
