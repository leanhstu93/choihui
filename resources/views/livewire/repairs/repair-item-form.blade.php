@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600'; @endphp

<div class="space-y-4">
    <a href="{{ $itemId ? route('repairs.show', $itemId) : route('repairs.index') }}" wire:navigate class="text-sm text-emerald-700">‹ Quay lại</a>
    <h1 class="text-xl font-semibold">{{ $itemId ? 'Sửa món đồ' : 'Thêm món đồ' }}</h1>

    <form wire:submit="save" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <x-field label="Tên món đồ" name="name" hint="Ví dụ: Xe SH, iPhone 13, Laptop Dell">
            <input id="name" type="text" wire:model="name" class="{{ $inputClass }}" required>
        </x-field>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="tracksKm" class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600">
            Đây là xe, ghi thêm số km mỗi lần sửa
        </label>
        <x-field label="Ghi chú" name="note">
            <input id="note" type="text" wire:model="note" class="{{ $inputClass }}">
        </x-field>
        <button type="submit" class="w-full rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white">Lưu</button>
    </form>
</div>
