<div>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Dây hụi</h1>
        <a href="{{ route('host.groups.create') }}" wire:navigate class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">+ Thêm dây</a>
    </div>

    <div class="mb-4 flex gap-2 overflow-x-auto" role="tablist">
        @foreach ($this->statusFilters() as $value => $label)
            <button type="button" wire:click="$set('status', '{{ $value }}')" role="tab" aria-selected="{{ $status === $value ? 'true' : 'false' }}"
                @class(['shrink-0 rounded-full px-3 py-1 text-sm border', 'bg-emerald-700 border-emerald-700 text-white' => $status === $value, 'bg-white border-gray-300 text-gray-700' => $status !== $value])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="space-y-3">
        @forelse ($this->groups as $group)
            <a href="{{ route('host.groups.show', $group) }}" wire:navigate wire:key="group-{{ $group->id }}" class="block rounded-xl bg-white p-4 shadow-sm active:bg-gray-50">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold">{{ $group->name }}</div>
                        <div class="text-sm text-gray-500">@money($group->face_value) · {{ $group->total_slots }} tay</div>
                    </div>
                    <span @class([
                        'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-50 text-emerald-700' => $group->status === \App\Enums\HuiGroupStatus::DangChay,
                        'bg-gray-100 text-gray-600' => $group->status === \App\Enums\HuiGroupStatus::KetThuc,
                        'bg-red-50 text-red-700' => $group->status === \App\Enums\HuiGroupStatus::Be,
                    ])>{{ $group->status->label() }}</span>
                </div>

                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <span>Đã hốt {{ $group->recorded_rounds_count }}/{{ $group->total_slots }} kỳ</span>
                    @if ($group->uncollected_count > 0)
                        <span class="font-medium text-orange-700">{{ $group->uncollected_count }} khoản chưa thu</span>
                    @else
                        <span class="text-emerald-700">Đã thu đủ</span>
                    @endif
                    @if ($group->unpaid_out_count > 0)
                        <span class="font-medium text-sky-700">{{ $group->unpaid_out_count }} khoản chưa chi</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm">
                Chưa có dây hụi nào.
                <a href="{{ route('host.groups.create') }}" wire:navigate class="font-semibold text-emerald-700">Tạo dây đầu tiên</a>
            </div>
        @endforelse
    </div>
</div>
