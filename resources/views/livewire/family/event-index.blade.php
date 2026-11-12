@php
    $badgeClass = fn (int $daysLeft) => match (true) {
        $daysLeft === 0 => 'bg-amber-400 text-amber-950',
        $daysLeft <= 7 => 'bg-emerald-100 text-emerald-800',
        default => 'bg-gray-100 text-gray-600',
    };
    $iconClass = [
        'gio' => 'bg-amber-50',
        'sinh_nhat' => 'bg-pink-50',
        'ky_niem' => 'bg-violet-50',
        'khac' => 'bg-sky-50',
    ];
@endphp

<div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold">Ngày nhớ</h1>
            <p class="text-sm text-gray-500">Giỗ, sinh nhật, kỷ niệm của gia đình</p>
        </div>
        @if ($hasEvents)
            <a href="{{ route('family.events.create') }}" wire:navigate class="shrink-0 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">+ Thêm ngày</a>
        @endif
    </div>

    {{-- Hôm nay: dương và âm lịch --}}
    <section class="flex items-center gap-4 rounded-2xl bg-gradient-to-br from-emerald-700 to-emerald-800 p-4 text-white shadow-sm">
        <div class="flex h-14 w-14 shrink-0 flex-col items-center justify-center rounded-xl bg-white/15">
            <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-100">Th{{ $today->month }}</span>
            <span class="text-xl font-bold leading-none">{{ $today->day }}</span>
        </div>
        <div class="min-w-0">
            <div class="text-sm text-emerald-100">Hôm nay · @weekday($today)</div>
            <div class="text-lg font-semibold">@date($today) · {{ $todayLunar }}</div>
        </div>
    </section>

    @if (! $hasEvents)
        {{-- Chưa có gì: giải thích ngắn và nút bắt đầu nhanh theo loại --}}
        <section class="rounded-2xl bg-white p-6 text-center shadow-sm">
            <div class="text-5xl" aria-hidden="true">🗓️</div>
            <h2 class="mt-3 text-lg font-semibold">Chưa có ngày nhớ nào</h2>
            <p class="mx-auto mt-1 max-w-md text-sm text-gray-600">Ghi một lần, năm nào app cũng tự tính ngày và gửi email nhắc trước. Ngày âm tự đổi sang dương.</p>
            <div class="mx-auto mt-5 grid max-w-md gap-2 sm:grid-cols-3">
                @foreach ([[\App\Enums\FamilyEventKind::Gio, 'Thêm ngày giỗ'], [\App\Enums\FamilyEventKind::SinhNhat, 'Thêm sinh nhật'], [\App\Enums\FamilyEventKind::KyNiem, 'Thêm kỷ niệm']] as [$kindOption, $startLabel])
                    <a href="{{ route('family.events.create', ['loai' => $kindOption->value]) }}" wire:navigate class="flex items-center justify-center gap-2 rounded-xl border border-gray-200 px-3 py-3 text-sm font-semibold text-gray-800 transition hover:border-emerald-400 hover:bg-emerald-50 sm:flex-col sm:gap-1">
                        <span class="text-2xl" aria-hidden="true">{{ $kindOption->icon() }}</span>
                        {{ $startLabel }}
                    </a>
                @endforeach
            </div>
            <a href="{{ route('family.events.create', ['loai' => \App\Enums\FamilyEventKind::Khac->value]) }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-emerald-700 underline-offset-2 hover:underline">Ngày khác (rằm, ngày lễ…)</a>
        </section>
    @endif

    @foreach ($groups as $groupTitle => $occurrences)
        <section class="space-y-2">
            <h2 class="px-1 text-sm font-semibold text-gray-500">{{ $groupTitle }}</h2>
            <ul class="divide-y divide-gray-100 rounded-2xl bg-white shadow-sm">
                @foreach ($occurrences as $occurrence)
                    @php $event = $occurrence->event; @endphp
                    <li wire:key="event-{{ $event->id }}">
                        <a href="{{ route('family.events.edit', $event) }}" wire:navigate @class(['flex items-center gap-3 p-4 transition hover:bg-gray-50', 'bg-amber-50/60' => $occurrence->daysLeft === 0])>
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-xl {{ $iconClass[$event->kind->value] }}" title="{{ $event->kind->label() }}">
                                <span aria-hidden="true">{{ $event->kind->icon() }}</span>
                                <span class="sr-only">{{ $event->kind->label() }}</span>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="truncate font-medium">{{ $event->title }}</div>
                                <div class="text-sm text-gray-500"><span class="whitespace-nowrap">@weekday($occurrence->date), @date($occurrence->date)</span> <span class="whitespace-nowrap">· {{ $occurrence->lunarLabel }}</span></div>
                                @if ($occurrence->yearsLabel)
                                    <div class="text-sm font-medium text-gray-700">{{ $occurrence->yearsLabel }}</div>
                                @endif
                            </div>
                            <span class="shrink-0 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass($occurrence->daysLeft) }}">{{ $occurrence->countdown() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
