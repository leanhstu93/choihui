<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Ngày nhớ</h1>
        <a href="{{ route('family.events.create') }}" wire:navigate class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">+ Thêm ngày</a>
    </div>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <div class="text-xs text-gray-500">Hôm nay</div>
        <div class="font-semibold">@date($today) · {{ $todayLunar }}</div>
    </section>

    <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
        @forelse ($occurrences as $occurrence)
            @php $event = $occurrence->event; @endphp
            <li wire:key="event-{{ $event->id }}">
                <a href="{{ route('family.events.edit', $event) }}" wire:navigate class="flex items-start justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $event->title }}</div>
                        <div class="text-sm text-gray-500">
                            @date($occurrence->date) · {{ $occurrence->lunarLabel }}
                        </div>
                        <div class="text-sm text-gray-500">
                            {{ $event->kind->label() }}{{ $occurrence->yearsLabel ? ' · '.$occurrence->yearsLabel : '' }}
                        </div>
                    </div>
                    <div @class(['shrink-0 text-sm font-medium', 'text-emerald-700' => $occurrence->daysLeft <= 7, 'text-gray-600' => $occurrence->daysLeft > 7])>
                        {{ $occurrence->countdown() }}
                    </div>
                </a>
            </li>
        @empty
            <li class="p-6 text-center text-sm text-gray-500">Chưa có ngày nhớ nào. Thêm ngày giỗ, sinh nhật, kỷ niệm (âm hoặc dương lịch) để được nhắc trước.</li>
        @endforelse
    </ul>
</div>
