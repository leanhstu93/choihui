@php
    use App\Livewire\Tasks\TaskBoard;
    use App\Queries\TaskAgenda;
    use App\Queries\TaskGroup;

    $weekdayName = fn (\DateTimeInterface $date) => \App\Models\Task::WEEKDAYS[(int) $date->format('w')];
    $lunarOf = fn ($date) => \App\Domain\LunarCalendar::fromSolar($date)->label();
    $groupTitle = function (TaskGroup $group) use ($today, $weekdayName, $lunarOf): string {
        return match ($group->key) {
            TaskGroup::OVERDUE => 'Quá hạn',
            TaskGroup::TODAY => 'Hôm nay',
            TaskGroup::TOMORROW => 'Ngày mai · '.$weekdayName($group->date).', '.$group->date->format('d/m').' · '.$lunarOf($group->date),
            TaskGroup::DAY => $weekdayName($group->date).', '.$group->date->format('d/m').' · '.$lunarOf($group->date),
            TaskGroup::LATER => 'Sau này',
            TaskGroup::SOMEDAY => 'Chưa có hạn',
            TaskGroup::DONE_ON => match (true) {
                $group->date->isSameDay($today) => 'Hôm nay',
                $group->date->isSameDay($today->subDay()) => 'Hôm qua',
                default => $weekdayName($group->date).', '.$group->date->format('d/m/Y'),
            },
            default => '',
        };
    };
    $groupTone = [TaskGroup::OVERDUE => 'text-red-700', TaskGroup::TODAY => 'text-amber-700'];
    $chipKindIcon = ['date' => '📅', 'time' => '🕗', 'important' => '⭐', 'repeat' => '🔁'];
    $hour = now()->hour;
    $greeting = match (true) { $hour < 11 => 'Chào buổi sáng', $hour < 13 => 'Chào buổi trưa', $hour < 18 => 'Chào buổi chiều', default => 'Chào buổi tối' };
    $firstName = \Illuminate\Support\Str::afterLast(trim(auth()->user()->name), ' ');
    $groups = $page['groups'];
    $isToday = $view === TaskBoard::VIEW_TODAY;
    $isDoneView = $view === TaskBoard::VIEW_DONE;
    $total = $page['totalToday'] ?? 0;
    $doneToday = $page['doneToday'] ?? 0;
    $percent = $total > 0 ? $doneToday / $total : 0;
    $chipClass = fn (bool $on) => $on
        ? 'border-emerald-600 bg-emerald-50 font-semibold text-emerald-800 ring-1 ring-emerald-600'
        : 'border-gray-200 bg-white text-gray-700 hover:border-emerald-300';
    $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600';
@endphp

<div class="space-y-4" data-task-board x-data="taskBoard" x-on:keydown.window="onKey($event)">
    {{-- Đầu trang --}}
    @if ($isToday)
        <section class="flex items-center gap-4 rounded-2xl bg-gradient-to-br from-emerald-700 to-emerald-800 p-4 text-white shadow-sm">
            @if ($total === 0)
                <div class="flex h-16 w-16 shrink-0 flex-col items-center justify-center rounded-xl bg-white/15">
                    <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-100">Th{{ $today->month }}</span>
                    <span class="text-2xl font-bold leading-none">{{ $today->day }}</span>
                </div>
            @else
            <div class="relative h-16 w-16 shrink-0" role="img" aria-label="Đã xong {{ $doneToday }} trên {{ $total }} việc hôm nay">
                <svg class="h-16 w-16 -rotate-90" viewBox="0 0 36 36" aria-hidden="true">
                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="currentColor" stroke-opacity=".2" stroke-width="3.5" />
                    <circle cx="18" cy="18" r="15.5" fill="none" stroke="#fcd34d" stroke-width="3.5" stroke-linecap="round"
                            stroke-dasharray="{{ round(97.4 * $percent, 1) }} 97.4" class="transition-[stroke-dasharray] duration-500 motion-reduce:transition-none" />
                </svg>
                <span class="absolute inset-0 flex items-center justify-center text-sm font-bold">{{ $doneToday }}/{{ $total }}</span>
            </div>
            @endif
            <div class="min-w-0">
                <div class="text-sm text-emerald-100">{{ $greeting }}, {{ $firstName }}</div>
                <h1 class="text-lg font-semibold leading-tight">{{ $weekdayName($today) }}, {{ $today->format('d/m/Y') }}</h1>
                <div class="text-sm text-emerald-100">{{ $todayLunar }} ·
                    @if ($total === 0)
                        chưa có việc hôm nay
                    @elseif ($doneToday === $total)
                        xong hết rồi!
                    @else
                        còn {{ $total - $doneToday }} việc
                    @endif
                </div>
            </div>
        </section>
    @else
        <div>
            <h1 class="text-xl font-semibold">{{ $isDoneView ? 'Đã xong' : 'Tất cả việc' }}</h1>
            <p class="text-sm text-gray-500">{{ $isDoneView ? 'Mới xong lên đầu. Việc xong quá '.\App\Models\Task::KEEP_DONE_DAYS.' ngày sẽ tự xóa.' : 'Sắp theo hạn: quá hạn, hôm nay, 7 ngày tới, sau này, chưa có hạn.' }}</p>
        </div>
    @endif

    {{-- Thêm nhanh (VL-01) --}}
    @unless ($isDoneView)
        <form wire:submit="add" class="rounded-2xl bg-white p-3 shadow-sm sm:p-4">
            <label for="quick-add" class="sr-only">Thêm việc</label>
            <div class="flex items-center gap-2">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 border-dashed border-emerald-400 text-emerald-600" aria-hidden="true">+</span>
                <input id="quick-add" x-ref="quickAdd" type="text" wire:model.live.debounce.200ms="draft" autocomplete="off" enterkeyhint="done" maxlength="250"
                       placeholder="Thêm việc, vd: Đi chợ mai 8h"
                       class="min-w-0 flex-1 border-0 px-0 py-1.5 text-base placeholder:text-gray-400 focus:ring-0">
                <button type="submit" class="shrink-0 rounded-lg bg-emerald-700 px-3.5 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:opacity-40"
                        @disabled(trim($draft) === '') wire:loading.attr="disabled" wire:target="add">Thêm</button>
            </div>

            @if ($parsed && $parsed->matches !== [])
                <div class="mt-2 flex flex-wrap items-center gap-1.5 pl-9" aria-live="polite">
                    <span class="text-xs text-gray-500">Đã hiểu:</span>
                    @foreach ($parsed->matches as $match)
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 py-1 pl-2.5 pr-1 text-xs font-semibold text-emerald-800">
                            <span aria-hidden="true">{{ $chipKindIcon[$match['kind']] }}</span>
                            @switch($match['kind'])
                                @case('date') {{ $parsed->dueOn ? $weekdayName($parsed->dueOn).', '.$parsed->dueOn->format('d/m') : $match['text'] }} @break
                                @case('time') {{ $parsed->dueTime }} @break
                                @case('important') Quan trọng @break
                                @case('repeat') {{ $parsed->repeat->label() }}@if ($parsed->repeat === \App\Enums\TaskRepeat::MonthlyLunar), ngày {{ $parsed->repeatDay }} âm @endif @break
                            @endswitch
                            <button type="button" wire:click="dismissChip('{{ $match['kind'] }}')" class="flex h-5 w-5 items-center justify-center rounded-full hover:bg-emerald-100" aria-label="Bỏ: {{ $match['text'] }}, giữ trong tên việc">×</button>
                        </span>
                    @endforeach
                    @if ($parsed->repeat !== \App\Enums\TaskRepeat::None && $parsed->dueOn && ! collect($parsed->matches)->contains('kind', 'date'))
                        <span class="text-xs text-gray-500">· bắt đầu {{ $weekdayName($parsed->dueOn) }}, {{ $parsed->dueOn->format('d/m') }}</span>
                    @endif
                </div>
            @elseif (trim($draft) === '')
                <div class="mt-2 flex flex-wrap gap-1.5 pl-9">
                    @foreach (['hôm nay' => 'Hôm nay', 'mai' => 'Mai', 'cuối tuần' => 'Cuối tuần', 'tuần sau' => 'Tuần sau'] as $word => $chipLabel)
                        <button type="button" x-on:click="$wire.draft = ($wire.draft.trim() + ' {{ $word }}').trim() + ' '; $refs.quickAdd.focus()" class="rounded-full border border-gray-200 px-2.5 py-1 text-xs text-gray-600 hover:border-emerald-300 hover:text-emerald-800">{{ $chipLabel }}</button>
                    @endforeach
                    <label class="relative cursor-pointer rounded-full border border-gray-200 px-2.5 py-1 text-xs text-gray-600 hover:border-emerald-300">
                        Chọn ngày
                        <input type="date" class="absolute inset-0 cursor-pointer opacity-0"
                               x-on:change="if ($event.target.value) { const [y, m, d] = $event.target.value.split('-'); $wire.draft = ($wire.draft.trim() + ` ${d}/${m}/${y}`).trim() + ' '; $refs.quickAdd.focus() }">
                    </label>
                    <span class="hidden items-center text-xs text-gray-400 sm:inline-flex">· gõ <kbd class="mx-1 rounded border px-1">?</kbd> xem phím tắt</span>
                </div>
            @endif

            @if ($actionError && ! $editing)
                <p class="mt-2 pl-9 text-sm text-red-600" role="alert">{{ $actionError }}</p>
            @endif
        </form>
    @endunless

    {{-- Tìm và lọc (V12, VL-04) --}}
    @unless ($isToday)
        <div class="space-y-2">
            <label class="relative block">
                <span class="sr-only">Tìm việc</span>
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="m21 21-4.3-4.3M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14Z" /></svg>
                <input type="search" x-ref="search" wire:model.live.debounce.300ms="search" placeholder="Tìm trong tên, ghi chú, các bước…" class="{{ $inputClass }} pl-9">
            </label>
            @unless ($isDoneView)
                <div class="flex flex-wrap gap-1.5">
                    @foreach ([TaskAgenda::FILTER_IMPORTANT => '⭐ Quan trọng', TaskAgenda::FILTER_RECURRING => '🔁 Có lặp', TaskAgenda::FILTER_SOMEDAY => '🗂️ Chưa có hạn'] as $filter => $filterLabel)
                        <button type="button" wire:click="toggleFilter('{{ $filter }}')" aria-pressed="{{ in_array($filter, $filters, true) ? 'true' : 'false' }}"
                                class="rounded-full border px-3 py-1.5 text-xs transition {{ $chipClass(in_array($filter, $filters, true)) }}">{{ $filterLabel }}</button>
                    @endforeach
                </div>
            @endunless
        </div>
    @endunless

    {{-- Dời hết quá hạn (V9) --}}
    @if ($isToday && $overdueCount >= 2)
        <div class="flex items-center justify-between gap-3 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm">
            <span class="text-red-800">Có <strong>{{ $overdueCount }} việc quá hạn</strong>. Làm hôm nay luôn?</span>
            <button type="button" wire:click="rescheduleOverdue" class="shrink-0 rounded-lg bg-white px-3 py-1.5 font-semibold text-red-700 shadow-sm ring-1 ring-red-200 hover:bg-red-100">Dời sang hôm nay</button>
        </div>
    @endif

    {{-- Trạng thái trống (VL-08) --}}
    @if (! $hasAny)
        <section class="rounded-2xl bg-white p-6 text-center shadow-sm">
            <div class="text-5xl" aria-hidden="true">📝</div>
            <h2 class="mt-3 text-lg font-semibold">Ghi việc cần làm, không lo quên</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-600">Gõ một dòng như ghi giấy, app tự hiểu ngày, giờ và việc lặp lại, kể cả theo lịch âm. Ví dụ:</p>
            <ul class="mx-auto mt-3 max-w-sm space-y-1 text-left text-sm text-gray-700">
                <li class="rounded-lg bg-gray-50 px-3 py-2"><code>Đóng tiền điện mai 8h !</code> <span class="text-gray-500">→ mai, 08:00, quan trọng</span></li>
                <li class="rounded-lg bg-gray-50 px-3 py-2"><code>Thắp hương rằm hằng tháng</code> <span class="text-gray-500">→ mỗi rằm âm lịch</span></li>
                <li class="rounded-lg bg-gray-50 px-3 py-2"><code>Họp phụ huynh thứ 6 tuần sau 19h</code></li>
            </ul>
            <p class="mt-5 text-sm font-medium text-gray-700">Hoặc bấm để thêm ngay:</p>
            <div class="mx-auto mt-2 flex max-w-md flex-wrap justify-center gap-2">
                @foreach (array_keys(TaskBoard::SUGGESTIONS) as $suggestion)
                    <button type="button" wire:click="addSuggestion(@js($suggestion))" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-800 transition hover:border-emerald-400 hover:bg-emerald-50">{{ $suggestion }}</button>
                @endforeach
            </div>
        </section>
    @elseif ($groups === [])
        <section class="rounded-2xl bg-white p-6 text-center shadow-sm">
            @if ($isToday && $total > 0)
                <div class="text-5xl" aria-hidden="true">🎉</div>
                <h2 class="mt-3 text-lg font-semibold">Xong hết việc hôm nay!</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $doneToday }} việc đã xong. Nghỉ ngơi chút nhé.</p>
            @elseif ($isToday)
                <div class="text-5xl" aria-hidden="true">🌿</div>
                <h2 class="mt-3 text-lg font-semibold">Hôm nay rảnh</h2>
                @if ($page['nextTask'])
                    <p class="mt-1 text-sm text-gray-600">Việc gần nhất: <strong>{{ $page['nextTask']->title }}</strong> · {{ $page['nextTask']->dueBadge($today)['label'] ?? '' }}</p>
                @endif
                <a href="{{ route('tasks.all') }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-emerald-700 hover:underline">Xem tất cả việc ›</a>
            @elseif (trim($search) !== '' || $filters !== [])
                <div class="text-4xl" aria-hidden="true">🔍</div>
                <h2 class="mt-3 font-semibold">Không có việc nào khớp</h2>
                <button type="button" wire:click="$set('search', ''); $set('filters', [])" class="mt-2 text-sm font-medium text-emerald-700 hover:underline">Xóa tìm kiếm và bộ lọc</button>
            @elseif ($isDoneView)
                <div class="text-4xl" aria-hidden="true">✅</div>
                <h2 class="mt-3 font-semibold">Chưa có việc nào xong</h2>
                <p class="mt-1 text-sm text-gray-600">Bấm vào ô tròn trước tên việc để đánh dấu xong.</p>
            @else
                <div class="text-4xl" aria-hidden="true">✨</div>
                <h2 class="mt-3 font-semibold">Không còn việc nào phải làm</h2>
            @endif
        </section>
    @endif

    {{-- Các nhóm việc (V3) --}}
    @foreach ($groups as $group)
        <section class="space-y-2" wire:key="group-{{ $group->key }}-{{ $group->date?->toDateString() }}">
            <h2 class="flex items-center gap-2 px-1 text-sm font-semibold {{ $groupTone[$group->key] ?? 'text-gray-500' }}">
                {{ $groupTitle($group) }}
                <span class="rounded-full bg-gray-200/70 px-1.5 text-xs font-semibold text-gray-600">{{ count($group->tasks) }}</span>
            </h2>
            <ul class="divide-y divide-gray-100 overflow-visible rounded-2xl bg-white shadow-sm">
                @foreach ($group->tasks as $task)
                    @include('livewire.tasks.partials.task-row', ['task' => $task, 'showDate' => ! in_array($group->key, [TaskGroup::TODAY, TaskGroup::TOMORROW, TaskGroup::DAY], true)])
                @endforeach
            </ul>
        </section>
    @endforeach

    {{-- Nút + nổi trên điện thoại --}}
    @unless ($isDoneView)
        <button type="button" x-on:click="window.scrollTo({ top: 0, behavior: 'smooth' }); $refs.quickAdd.focus()"
                class="fixed bottom-20 right-4 z-10 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-700 text-3xl text-white shadow-lg hover:bg-emerald-800 sm:hidden" style="margin-bottom: env(safe-area-inset-bottom)" aria-label="Thêm việc">+</button>
    @endunless

    {{-- Khung chi tiết (VL-02): bên phải trên máy tính, trượt từ dưới lên trên điện thoại --}}
    @if ($editing)
        <div class="fixed inset-0 z-30" role="dialog" aria-modal="true" aria-labelledby="task-editor-title" wire:key="editor-{{ $editing->id }}"
             x-data="{ confirmDelete: false, stepsDone: false }" x-on:task-steps-done.window="stepsDone = true"
             x-init="$nextTick(() => $refs.editorTitle?.focus())">
            <div class="absolute inset-0 bg-gray-900/40" wire:click="close" aria-hidden="true"></div>
            <div class="absolute inset-x-0 bottom-0 flex max-h-[92vh] flex-col rounded-t-2xl bg-white shadow-xl sm:inset-y-0 sm:left-auto sm:right-0 sm:max-h-none sm:w-[440px] sm:rounded-none">
                <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                    <h2 id="task-editor-title" class="font-semibold">Chi tiết việc</h2>
                    <button type="button" wire:click="close" class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Đóng">✕</button>
                </div>

                <form wire:submit="save" class="flex-1 space-y-5 overflow-y-auto px-4 py-4">
                    <div class="flex items-start gap-2">
                        <x-field label="Tên việc" name="title" class="flex-1">
                            <textarea id="title" x-ref="editorTitle" rows="2" wire:model="title" maxlength="200" class="{{ $inputClass }} resize-none text-base font-medium" required></textarea>
                        </x-field>
                        <button type="button" wire:click="$toggle('important')" aria-pressed="{{ $important ? 'true' : 'false' }}"
                                class="mt-6 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border text-xl transition {{ $important ? 'border-amber-400 bg-amber-50 text-amber-500' : 'border-gray-200 text-gray-300 hover:text-amber-400' }}"
                                title="Quan trọng"><span aria-hidden="true">★</span><span class="sr-only">Quan trọng</span></button>
                    </div>

                    {{-- Hạn --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Hạn</legend>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @php $today2 = $today->toDateString(); $tomorrow = $today->addDay()->toDateString(); @endphp
                            @foreach (['today' => ['Hôm nay', $today2], 'tomorrow' => ['Mai', $tomorrow], 'weekend' => ['Cuối tuần', null], 'next_week' => ['Tuần sau', null], 'none' => ['Không có hạn', '']] as $option => [$optionLabel, $optionDate])
                                <button type="button" wire:click="setDue('{{ $option }}')" class="rounded-full border px-3 py-1.5 text-xs transition {{ $chipClass($optionDate !== null && $dueOn === $optionDate) }}">{{ $optionLabel }}</button>
                            @endforeach
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <label class="block">
                                <span class="text-xs text-gray-500">Ngày</span>
                                <input type="date" wire:model.live="dueOn" class="{{ $inputClass }}">
                            </label>
                            <label class="block">
                                <span class="text-xs text-gray-500">Giờ (không bắt buộc)</span>
                                <input type="time" wire:model.live="dueTime" class="{{ $inputClass }}" @disabled($dueOn === '')>
                            </label>
                        </div>
                        @if ($dueOn !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueOn))
                            @php $dueDate = \Carbon\CarbonImmutable::parse($dueOn); @endphp
                            <p class="mt-1 text-xs text-gray-500">{{ $weekdayName($dueDate) }}, {{ $dueDate->format('d/m/Y') }} · {{ $lunarOf($dueDate) }}</p>
                        @endif
                        @error('due_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @error('due_time') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </fieldset>

                    {{-- Lặp lại --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Lặp lại</legend>
                        <div class="mt-2 grid grid-cols-3 gap-1.5">
                            @foreach (\App\Enums\TaskRepeat::cases() as $repeatOption)
                                <button type="button" wire:click="setRecurrence('{{ $repeatOption->value }}')" class="rounded-lg border px-2 py-2 text-xs transition {{ $chipClass($recurrence === $repeatOption->value) }}">{{ $repeatOption->label() }}</button>
                            @endforeach
                        </div>
                        @if ($recurrence === 'weekly')
                            <div class="mt-2 flex gap-1" role="group" aria-label="Các thứ trong tuần">
                                @foreach ([1 => 'T2', 2 => 'T3', 3 => 'T4', 4 => 'T5', 5 => 'T6', 6 => 'T7', 7 => 'CN'] as $iso => $dayLabel)
                                    <button type="button" wire:click="toggleWeekday({{ $iso }})" aria-pressed="{{ in_array($iso, $weekdays, true) ? 'true' : 'false' }}"
                                            class="h-10 flex-1 rounded-lg border text-xs transition {{ $chipClass(in_array($iso, $weekdays, true)) }}">{{ $dayLabel }}</button>
                                @endforeach
                            </div>
                        @endif
                        @error('repeat_weekdays') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        @if ($preview !== [])
                            <div class="mt-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                                <div class="font-semibold">Các lần tới</div>
                                <ul class="mt-1 space-y-0.5">
                                    @foreach ($preview as $next)
                                        <li>{{ $weekdayName($next) }}, {{ $next->format('d/m/Y') }} <span class="text-emerald-700">· {{ $lunarOf($next) }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </fieldset>

                    {{-- Các bước nhỏ (V5) --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-gray-700">Các bước <span class="font-normal text-gray-400">({{ $editing->steps->count() }}/{{ \App\Models\Task::MAX_STEPS }})</span></legend>
                        <ul class="mt-2 divide-y divide-gray-100 rounded-lg border border-gray-200">
                            @foreach ($editing->steps as $step)
                                <li class="flex items-center gap-2 px-2 py-1.5" wire:key="step-{{ $step->id }}">
                                    <input type="checkbox" @checked($step->done) wire:click="toggleStep('{{ $step->id }}', {{ $step->done ? 'false' : 'true' }})"
                                           class="h-5 w-5 rounded border-gray-300 text-emerald-600 focus:ring-emerald-600" aria-label="Xong bước: {{ $step->title }}">
                                    <input type="text" value="{{ $step->title }}" maxlength="200" x-on:change="$wire.renameStep(@js($step->id), $event.target.value)"
                                           @class(['min-w-0 flex-1 border-0 px-1 py-1 text-sm focus:ring-1 focus:ring-emerald-600', 'text-gray-400 line-through' => $step->done]) aria-label="Tên bước">
                                    <button type="button" wire:click="deleteStep('{{ $step->id }}')" class="flex h-8 w-8 items-center justify-center rounded text-gray-400 hover:bg-red-50 hover:text-red-600" aria-label="Xóa bước: {{ $step->title }}">✕</button>
                                </li>
                            @endforeach
                            @if ($editing->steps->count() < \App\Models\Task::MAX_STEPS)
                                <li class="flex items-center gap-2 px-2 py-1.5">
                                    <span class="flex h-5 w-5 items-center justify-center text-gray-400" aria-hidden="true">+</span>
                                    <input type="text" wire:model="newStep" wire:keydown.enter.prevent="addStep" maxlength="200" placeholder="Thêm bước, Enter để lưu" class="min-w-0 flex-1 border-0 px-1 py-1 text-sm focus:ring-0" aria-label="Thêm bước">
                                </li>
                            @endif
                        </ul>
                        <div x-show="stepsDone" x-cloak class="mt-2 flex items-center justify-between gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                            <span>Xong hết các bước. Đánh dấu xong việc?</span>
                            <button type="button" class="shrink-0 rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white"
                                    x-on:click="$wire.complete(@js($editing->id)).then((r) => { window.dispatchEvent(new CustomEvent('task-toast', { detail: { message: r.message, undoId: @js($editing->id) } })); $wire.$refresh() })">Xong việc</button>
                        </div>
                    </fieldset>

                    <x-field label="Ghi chú" name="note">
                        <textarea id="note" rows="3" wire:model="note" maxlength="2000" placeholder="Số hợp đồng, số điện thoại, đường link…" class="{{ $inputClass }}"></textarea>
                    </x-field>

                    @if ($actionError)
                        <p class="text-sm text-red-600" role="alert">{{ $actionError }}</p>
                    @endif

                    <p class="text-xs text-gray-400">Tạo lúc {{ $editing->created_at->format('H:i d/m/Y') }}</p>
                </form>

                <div class="flex items-center gap-2 border-t border-gray-100 px-4 py-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))">
                    <div class="relative">
                        <button type="button" x-on:click="confirmDelete = ! confirmDelete" class="rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">Xóa</button>
                        <div x-show="confirmDelete" x-cloak x-transition x-on:click.outside="confirmDelete = false" class="absolute bottom-12 left-0 w-60 rounded-xl border border-gray-100 bg-white p-2 text-sm shadow-lg">
                            @if ($editing->isRecurring())
                                <button type="button" wire:click="delete(true)" class="block w-full rounded-lg px-3 py-2 text-left hover:bg-gray-50">Chỉ xóa lần này<span class="block text-xs text-gray-500">Lịch lặp vẫn tiếp tục</span></button>
                                <button type="button" wire:click="delete(false)" class="block w-full rounded-lg px-3 py-2 text-left text-red-700 hover:bg-red-50">Xóa và dừng lặp</button>
                            @else
                                <p class="px-3 py-1 text-gray-600">Xóa việc này?</p>
                                <button type="button" wire:click="delete(false)" class="block w-full rounded-lg px-3 py-2 text-left font-semibold text-red-700 hover:bg-red-50">Xóa luôn</button>
                            @endif
                        </div>
                    </div>
                    <button type="button" wire:click="close" class="ml-auto rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Hủy</button>
                    <button type="button" wire:click="save" class="rounded-lg bg-emerald-700 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-800" wire:loading.attr="disabled" wire:target="save">Lưu</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Bảng phím tắt (V10) --}}
    <div x-show="help" x-cloak class="fixed inset-0 z-40 flex items-center justify-center bg-gray-900/40 p-4" x-on:click.self="help = false" role="dialog" aria-modal="true" aria-label="Phím tắt">
        <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-xl">
            <h2 class="font-semibold">Phím tắt</h2>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                @foreach ([[['N'], 'Thêm việc'], [['/'], 'Tìm việc'], [['↑', '↓'], 'Chọn việc'], [['X', 'Space'], 'Đánh dấu xong'], [['D'], 'Dời'], [['E', 'Enter'], 'Mở chi tiết'], [['Esc'], 'Đóng'], [['?'], 'Bật / tắt bảng này']] as [$keys, $meaning])
                    <dt class="flex items-center gap-1">@foreach ($keys as $key)<kbd class="rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 font-mono text-xs">{{ $key }}</kbd>@endforeach</dt>
                    <dd class="text-gray-700">{{ $meaning }}</dd>
                @endforeach
            </dl>
            <p class="mt-3 text-xs text-gray-500">Trên điện thoại: vuốt phải để xong, vuốt trái để dời.</p>
        </div>
    </div>

    {{-- Thanh báo có Hoàn tác (V8) --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-20 z-40 flex flex-col items-center gap-2 px-4 sm:bottom-6" x-data="taskToasts" x-on:task-toast.window="push($event.detail)" role="status" aria-live="polite">
        <template x-for="item in items" :key="item.key">
            <div class="pointer-events-auto flex w-full max-w-md items-center gap-3 rounded-xl px-4 py-3 text-sm text-white shadow-lg"
                 :class="item.tone === 'error' ? 'bg-red-700' : 'bg-gray-900'" x-transition.opacity>
                <span class="min-w-0 flex-1" x-text="item.message"></span>
                <button type="button" x-show="item.undoId" x-on:click="undo(item)" class="shrink-0 rounded-lg px-2 py-1 font-semibold text-amber-300 hover:bg-white/10">Hoàn tác</button>
                <button type="button" x-on:click="dismiss(item.key)" class="shrink-0 text-white/60 hover:text-white" aria-label="Đóng thông báo">✕</button>
            </div>
        </template>
    </div>
</div>
