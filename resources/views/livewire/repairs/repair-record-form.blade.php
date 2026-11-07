@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600'; @endphp

<div class="space-y-4">
    <a href="{{ route('repairs.show', $itemId) }}" wire:navigate class="text-sm text-emerald-700">‹ {{ $item->name }}</a>
    <h1 class="text-xl font-semibold">{{ $recordId ? 'Sửa lần sửa' : 'Thêm lần sửa' }}</h1>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif

    <form wire:submit="save" x-data="photoPicker" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <x-field label="Hạng mục" name="part" hint="Đặt cùng tên cho các lần thay cùng một thứ, app sẽ tính món đó dùng được bao lâu.">
            <input id="part" type="text" list="part-names" autocomplete="off" wire:model="part" class="{{ $inputClass }}" placeholder="Pin, Lốp sau, Thay nhớt..." required>
            <datalist id="part-names">
                @foreach ($partNames as $partName)
                    <option value="{{ $partName }}"></option>
                @endforeach
            </datalist>
        </x-field>
        <div class="grid grid-cols-2 gap-3">
            <x-field label="Ngày sửa" name="repaired_on">
                <x-date-input id="repaired_on" model="repairedOn" />
            </x-field>
            <x-field label="Số tiền" name="amount" hint="Miễn phí thì nhập 0.">
                <x-money-input id="amount" model="amount" />
            </x-field>
        </div>
        <x-field label="Nơi sửa" name="place">
            <input id="place" type="text" wire:model="place" class="{{ $inputClass }}">
        </x-field>
        <div class="grid grid-cols-2 gap-3">
            <x-field label="Bảo hành đến" name="warranty_until" hint="Bỏ trống nếu không có.">
                <x-date-input id="warranty_until" model="warrantyUntil" />
            </x-field>
            @if ($showKm)
                <x-field label="Số km trên xe" name="odometer_km">
                    <input id="odometer_km" type="text" inputmode="numeric" autocomplete="off" wire:model="odometerKm" class="{{ $inputClass }}">
                </x-field>
            @endif
        </div>
        <x-field label="Ghi chú" name="note">
            <input id="note" type="text" wire:model="note" class="{{ $inputClass }}">
        </x-field>
        <x-photo-field label="Ảnh hóa đơn" :saved-photos="$savedPhotos" :photos="$photos" photo-route="repairs.photos.show" hint="Tối đa {{ \App\Models\RepairRecord::MAX_PHOTOS }} ảnh. Chỉ mình bạn xem được." />
        <button type="submit" x-bind:disabled="busy" class="w-full rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white disabled:opacity-50">Lưu</button>
    </form>

    @if ($recordId)
        <button type="button" wire:click="deleteRecord" wire:confirm="Xóa lần sửa này và ảnh của nó?" class="w-full text-center text-sm text-red-600">Xóa lần sửa này</button>
    @endif
</div>
