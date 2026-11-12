<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Sức khỏe</h1>
        <a href="{{ route('family.health.people.create') }}" wire:navigate class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">+ Thêm người</a>
    </div>

    @if ($followUps->isNotEmpty())
        <section class="space-y-2">
            <h2 class="font-semibold">Lịch hẹn sắp tới</h2>
            <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                @foreach ($followUps as $record)
                    <li wire:key="follow-up-{{ $record->id }}">
                        <a href="{{ route('family.health.show', $record->health_person_id) }}" wire:navigate class="flex items-start justify-between gap-3 p-4 text-sm">
                            <div class="min-w-0">
                                <div class="font-medium">{{ $record->person->name }}</div>
                                <div class="truncate text-gray-500">Tái khám: {{ $record->title }}{{ $record->place ? ' · '.$record->place : '' }}</div>
                            </div>
                            <div @class(['shrink-0 text-right font-medium', 'text-emerald-700' => $record->follow_up_on->diffInDays($today, true) <= 7])>
                                @date($record->follow_up_on)
                                <div class="text-xs font-normal text-gray-500">{{ $record->follow_up_on->isSameDay($today) ? 'Hôm nay' : 'Còn '.(int) $today->diffInDays($record->follow_up_on).' ngày' }}</div>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($people->isNotEmpty())
        <section class="rounded-xl bg-white p-3 shadow-sm">
            <div class="text-xs text-gray-500">Chi phí khám năm {{ $year }}</div>
            <div class="font-semibold">@money($yearTotal)</div>
        </section>
    @endif

    <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
        @forelse ($people as $person)
            <li wire:key="person-{{ $person->id }}">
                <a href="{{ route('family.health.show', $person) }}" wire:navigate class="block p-4">
                    <div class="truncate font-medium">{{ $person->name }}{{ $person->age($year) !== null ? ' · '.$person->age($year).' tuổi' : '' }}</div>
                    <div class="text-sm text-gray-500">
                        @if ($person->records_count > 0)
                            {{ $person->records_count }} lần · gần nhất @date(\Carbon\CarbonImmutable::parse($person->records_max_visited_on))
                        @else
                            Chưa có lần khám nào
                        @endif
                    </div>
                </a>
            </li>
        @empty
            <li class="p-6 text-center text-sm text-gray-500">Chưa có ai trong sổ. Thêm từng người trong nhà để ghi lại các lần khám, tiêm, xét nghiệm.</li>
        @endforelse
    </ul>
</div>
