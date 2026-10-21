<div>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">Dây hụi</h1>
        <a href="{{ route('groups.create') }}" wire:navigate class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">+ Thêm dây</a>
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
            <a href="{{ route('groups.show', $group) }}" wire:navigate wire:key="group-{{ $group->id }}" class="block rounded-xl bg-white p-4 shadow-sm active:bg-gray-50">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold">{{ $group->name }}</div>
                        <div class="text-sm text-gray-500">@money($group->face_value) · {{ $group->slots_count }} tay của mình</div>
                    </div>
                    <span @class([
                        'shrink-0 rounded-full px-2 py-0.5 text-xs font-medium',
                        'bg-emerald-50 text-emerald-700' => $group->status === \App\Enums\HuiGroupStatus::DangChay,
                        'bg-gray-100 text-gray-600' => $group->status === \App\Enums\HuiGroupStatus::KetThuc,
                        'bg-red-50 text-red-700' => $group->status === \App\Enums\HuiGroupStatus::Be,
                    ])>{{ $group->status->label() }}</span>
                </div>

                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <span>Kỳ {{ $group->recorded_rounds_count }}/{{ $group->total_slots }}</span>
                    <span>Đã hốt {{ $group->won_rounds_count }}/{{ $group->slots_count }} tay</span>
                    @if ($group->unpaid_entries_count > 0)
                        <span class="font-medium text-orange-700">{{ $group->unpaid_entries_count }} khoản chưa đóng</span>
                    @else
                        <span class="text-emerald-700">Đã đóng đủ</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm">
                Chưa có dây hụi nào.
                <a href="{{ route('groups.create') }}" wire:navigate class="font-semibold text-emerald-700">Thêm dây đầu tiên</a>
            </div>
        @endforelse
    </div>
</div>
