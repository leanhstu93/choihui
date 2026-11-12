@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600'; @endphp

<div class="space-y-4">
    <a href="{{ $personId ? route('family.health.show', $personId) : route('family.health.index') }}" wire:navigate class="text-sm text-emerald-700">‹ Quay lại</a>
    <h1 class="text-xl font-semibold">{{ $personId ? 'Sửa thông tin' : 'Thêm người' }}</h1>

    <form wire:submit="save" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <x-field label="Tên" name="name" hint="Ví dụ: Bố, Mẹ, Bé Na, Tôi">
            <input id="name" type="text" wire:model="name" class="{{ $inputClass }}" required>
        </x-field>
        <x-field label="Năm sinh" name="birth_year" hint="Không bắt buộc, để hiện tuổi.">
            <input id="birth_year" type="text" inputmode="numeric" maxlength="4" autocomplete="off" wire:model="birthYear" class="{{ $inputClass }}">
        </x-field>
        <x-field label="Ghi chú" name="note" hint="Nhóm máu, dị ứng thuốc, bệnh nền...">
            <input id="note" type="text" wire:model="note" class="{{ $inputClass }}">
        </x-field>
        <button type="submit" class="w-full rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white">Lưu</button>
    </form>
</div>
