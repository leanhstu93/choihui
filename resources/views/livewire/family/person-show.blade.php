<div class="space-y-4">
    <a href="{{ route('family.health.index') }}" wire:navigate class="text-sm text-emerald-700">‹ Sức khỏe</a>

    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold">{{ $healthPerson->name }}</h1>
            @if ($healthPerson->age($today->year) !== null)
                <p class="text-sm text-gray-500">{{ $healthPerson->age($today->year) }} tuổi (sinh năm {{ $healthPerson->birth_year }})</p>
            @endif
            @if ($healthPerson->note)
                <p class="text-sm text-gray-500">{{ $healthPerson->note }}</p>
            @endif
        </div>
        <a href="{{ route('family.health.people.edit', $healthPerson) }}" wire:navigate class="shrink-0 text-sm text-emerald-700">Sửa</a>
    </div>

    <section class="grid grid-cols-2 gap-2">
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Chi phí</div><div class="font-semibold">@money($total)</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Số lần</div><div class="font-semibold">{{ $records->count() }}</div></div>
    </section>

    @if ($followUps->isNotEmpty())
        <section class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            @foreach ($followUps as $record)
                <div wire:key="follow-up-{{ $record->id }}">Hẹn tái khám <strong>@date($record->follow_up_on)</strong>: {{ $record->title }}{{ $record->place ? ' · '.$record->place : '' }}</div>
            @endforeach
        </section>
    @endif

    <a href="{{ route('family.health.records.create', $healthPerson) }}" wire:navigate class="block w-full rounded-lg bg-emerald-700 py-2 text-center text-sm font-semibold text-white">+ Thêm lần khám</a>

    <section class="space-y-2">
        <h2 class="font-semibold">Lịch sử</h2>
        <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            @forelse ($records as $record)
                <li class="space-y-1 p-4 text-sm" wire:key="record-{{ $record->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 font-medium">{{ $record->title }}</div>
                        @if ($record->amount !== null)
                            <div class="shrink-0 font-medium">@money($record->amount)</div>
                        @endif
                    </div>
                    <div class="text-gray-500">@date($record->visited_on) · {{ $record->kind->label() }}{{ $record->place ? ' · '.$record->place : '' }}</div>
                    @if ($record->follow_up_on)
                        <div @class(['text-emerald-700' => $record->follow_up_on->greaterThanOrEqualTo($today), 'text-gray-500' => $record->follow_up_on->lessThan($today)])>
                            Hẹn tái khám @date($record->follow_up_on)
                        </div>
                    @endif
                    @if ($record->note)
                        <p class="whitespace-pre-line text-gray-600">{{ $record->note }}</p>
                    @endif
                    @if ($record->photos->isNotEmpty())
                        <div class="grid grid-cols-4 gap-2 pt-1">
                            @foreach ($record->photos as $photo)
                                <a href="{{ route('family.health.photos.show', $photo) }}" target="_blank" rel="noopener" wire:key="photo-{{ $photo->id }}">
                                    <img src="{{ route('family.health.photos.show', $photo) }}" alt="Ảnh đơn thuốc, kết quả" loading="lazy" class="aspect-square w-full rounded-lg bg-gray-200 object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ route('family.health.records.edit', $record) }}" wire:navigate class="inline-block pt-1 text-emerald-700">Sửa</a>
                </li>
            @empty
                <li class="p-6 text-center text-sm text-gray-500">Chưa có lần khám nào.</li>
            @endforelse
        </ul>
    </section>

    <button type="button" wire:click="deletePerson" wire:confirm="Xóa người này cùng toàn bộ lịch sử khám và ảnh? Không lấy lại được." class="w-full text-center text-sm text-red-600">Xóa người này</button>
</div>
