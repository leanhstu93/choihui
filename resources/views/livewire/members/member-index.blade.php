@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600'; @endphp

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Hụi viên</h1>
        @unless ($showForm)
            <button type="button" wire:click="create" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">+ Thêm hụi viên</button>
        @endunless
    </div>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif

    @if ($showForm)
        <form wire:submit="save" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">{{ $editingMemberId ? 'Sửa hụi viên' : 'Thêm hụi viên' }}</h2>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Tên" name="name">
                    <input id="name" type="text" wire:model="name" class="{{ $inputClass }}" placeholder="Chị Lan" required autofocus>
                </x-field>
                <x-field label="SĐT" name="phone">
                    <input id="phone" type="tel" wire:model="phone" class="{{ $inputClass }}">
                </x-field>
            </div>
            <x-field label="Ghi chú" name="note">
                <input id="note" type="text" wire:model="note" class="{{ $inputClass }}">
            </x-field>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white">Lưu</button>
                <button type="button" wire:click="cancel" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Hủy</button>
            </div>
        </form>
    @endif

    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Tìm theo tên hoặc SĐT" aria-label="Tìm hụi viên" class="{{ $inputClass }}">

    <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
        @forelse ($members as $member)
            <li class="flex items-center justify-between gap-2 p-4" wire:key="member-{{ $member->id }}">
                <a href="{{ route('host.members.show', $member) }}" wire:navigate class="min-w-0 flex-1">
                    <div class="font-medium">{{ $member->name }}</div>
                    <div class="text-sm text-gray-500">
                        {{ $member->phone ?: 'Chưa có SĐT' }} · {{ $member->hostSlots->count() }} tay
                        @if ($debts[$member->id] > 0)
                            · <span class="font-medium text-red-700">Nợ @money($debts[$member->id])</span>
                        @endif
                    </div>
                </a>
                <div class="flex shrink-0 gap-3 text-sm">
                    <button type="button" wire:click="edit('{{ $member->id }}')" class="text-emerald-700">Sửa</button>
                    @if ($member->hostSlots->isEmpty())
                        <button type="button" wire:click="delete('{{ $member->id }}')" wire:confirm="Xóa {{ $member->name }} khỏi danh bạ?" class="text-red-600">Xóa</button>
                    @endif
                </div>
            </li>
        @empty
            <li class="p-6 text-center text-sm text-gray-500">{{ $search === '' ? 'Chưa có hụi viên nào.' : 'Không tìm thấy hụi viên.' }}</li>
        @endforelse
    </ul>
</div>
