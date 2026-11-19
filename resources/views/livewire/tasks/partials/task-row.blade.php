{{-- Một dòng việc (VL-07): ô tròn, tên, sao, nhãn hạn, lặp, số bước, ghi chú; vuốt và menu dời (V9, V10). --}}
@php
    $badge = $task->dueBadge($today);
    $toneClass = [
        'late' => 'bg-red-50 text-red-700',
        'today' => 'bg-amber-100 text-amber-800',
        'soon' => 'bg-emerald-50 text-emerald-700',
        'later' => 'bg-gray-100 text-gray-600',
    ];
    $isDone = $task->isDone();
@endphp

<li wire:key="task-{{ $task->id }}" data-task-row data-task-id="{{ $task->id }}" tabindex="0"
    class="group relative outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-emerald-600"
    x-data="taskRow(@js($task->id), @js($isDone))"
    x-on:task-complete="complete()"
    x-on:task-menu="menu = true"
    x-on:task-error.window="revert()"
    x-on:keydown.escape="menu = false"
    :class="dx !== 0 && 'overflow-hidden'">
    {{-- Nền lộ ra khi vuốt: phải = xong (xanh), trái = dời (xanh dương) --}}
    <div class="absolute inset-0 flex items-center justify-between px-5 group-first:rounded-t-2xl group-last:rounded-b-2xl text-sm font-semibold text-white" aria-hidden="true" x-show="dx !== 0" x-cloak
         :class="dx > 0 ? 'bg-emerald-600' : 'bg-sky-600'">
        <span x-show="dx > 0">✓ Xong</span>
        <span x-show="dx < 0" class="ml-auto">Dời ⏰</span>
    </div>

    <div class="relative flex items-start gap-3 bg-white px-3 py-3 group-first:rounded-t-2xl group-last:rounded-b-2xl transition-[opacity,transform] duration-200 motion-reduce:transition-none sm:px-4"
         :style="dx !== 0 ? `transform: translateX(${dx}px)` : ''"
         :class="leaving ? 'opacity-0 -translate-y-1' : ''"
         x-on:touchstart.passive="touchStart($event)"
         x-on:touchmove="touchMove($event)"
         x-on:touchend="touchEnd()">
        @if ($isDone)
            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-white" aria-hidden="true">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
            </span>
        @else
            <button type="button" x-on:click="complete()"
                    class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-2 transition motion-reduce:transition-none before:absolute before:-inset-2 before:content-[''] relative"
                    :class="done ? 'border-emerald-600 bg-emerald-600 text-white' : '{{ $task->important ? 'border-amber-500 hover:bg-amber-50' : 'border-gray-300 hover:border-emerald-500 hover:bg-emerald-50' }}'"
                    aria-label="Đánh dấu xong: {{ $task->title }}">
                <svg x-show="done" x-cloak class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
            </button>
        @endif

        <button type="button" wire:click="open('{{ $task->id }}')" class="min-w-0 flex-1 text-left" @if ($isDone) disabled @endif>
            <span class="flex items-start gap-1.5">
                <span @class(['break-words font-medium leading-snug', 'text-gray-400 line-through' => $isDone]) :class="done && 'text-gray-400 line-through'">{{ $task->title }}</span>
                @if ($task->important)
                    <span class="shrink-0 text-amber-500" title="Quan trọng"><span aria-hidden="true">★</span><span class="sr-only">Quan trọng</span></span>
                @endif
            </span>
            <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                @if ($isDone)
                    <span>Xong lúc {{ $task->completed_at?->format('H:i') }}</span>
                    @if ($task->due_on)
                        <span>· hạn {{ $task->due_on->format('d/m') }}</span>
                    @endif
                @elseif ($badge && ($showDate ?? true))
                    <span class="rounded-full px-2 py-0.5 font-semibold {{ $toneClass[$badge['tone']] }}">{{ $badge['label'] }}</span>
                @elseif ($task->due_time)
                    <span class="rounded-full bg-gray-100 px-2 py-0.5 font-semibold text-gray-700">🕗 {{ $task->due_time }}</span>
                @endif
                @if ($task->isRecurring())
                    <span title="{{ $task->recurrenceLabel() }}">🔁 {{ $task->recurrenceLabel() }}</span>
                @endif
                @if (($task->steps_count ?? 0) > 0)
                    <span @class(['font-medium', 'text-emerald-700' => $task->steps_done_count === $task->steps_count])>☑ {{ $task->steps_done_count }}/{{ $task->steps_count }}</span>
                @endif
                @if ($task->note)
                    <span title="Có ghi chú">📝<span class="sr-only">Có ghi chú</span></span>
                @endif
            </span>
        </button>

        @if ($isDone)
            <button type="button" wire:click="reopen('{{ $task->id }}')" class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50">Làm lại</button>
        @else
            <div class="relative shrink-0">
                <button type="button" x-on:click="menu = ! menu" class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Dời: {{ $task->title }}" :aria-expanded="menu">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13v4.5l3 1.5" /></svg>
                </button>
                <div x-show="menu" x-cloak x-transition.origin.top.right x-on:click.outside="menu = false"
                     class="absolute right-0 top-10 z-20 w-52 overflow-hidden rounded-xl border border-gray-100 bg-white py-1 text-sm shadow-lg" role="menu">
                    <p class="px-3 pb-1 pt-1.5 text-xs font-semibold text-gray-400">Dời sang</p>
                    @foreach (['this_afternoon' => '🌤️ Chiều nay (14:00)', 'tomorrow' => '☀️ Ngày mai', 'weekend' => '🏖️ Cuối tuần', 'next_week' => '📅 Tuần sau (thứ 2)'] as $option => $optionLabel)
                        <button type="button" role="menuitem" wire:click="reschedule('{{ $task->id }}', '{{ $option }}')" x-on:click="menu = false" class="block w-full px-3 py-2 text-left hover:bg-gray-50">{{ $optionLabel }}</button>
                    @endforeach
                    <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50">
                        <span>🗓️ Chọn ngày</span>
                        <input type="date" class="ml-auto w-0 flex-1 rounded border-gray-200 p-1 text-xs" x-on:change="$wire.reschedule(@js($task->id), $event.target.value); menu = false">
                    </label>
                    @unless ($task->isRecurring())
                        <button type="button" role="menuitem" wire:click="reschedule('{{ $task->id }}', 'none')" x-on:click="menu = false" class="block w-full border-t border-gray-100 px-3 py-2 text-left text-gray-600 hover:bg-gray-50">Bỏ hạn</button>
                    @endunless
                </div>
            </div>
        @endif
    </div>
</li>
