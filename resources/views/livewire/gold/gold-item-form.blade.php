@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600'; @endphp

<div class="space-y-4">
    <a href="{{ $itemId ? route('gold.show', $itemId) : route('gold.index') }}" wire:navigate class="text-sm text-emerald-700">‹ Quay lại</a>
    <h1 class="text-xl font-semibold">{{ $itemId ? 'Sửa món vàng' : 'Thêm món vàng' }}</h1>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif

    <form wire:submit="save" x-data="photoPicker" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <x-field label="Loại vàng" name="gold_type">
            <select id="gold_type" wire:model="goldType" class="{{ $inputClass }}">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </select>
        </x-field>
        <x-field label="Tên món" name="name" hint="Không bắt buộc, ví dụ: Nhẫn cưới">
            <input id="name" type="text" wire:model="name" class="{{ $inputClass }}">
        </x-field>
        <div class="grid grid-cols-2 gap-3">
            <x-field label="Số chỉ" name="weight_ly" hint="1 lượng = 10 chỉ">
                <input id="weight_ly" type="text" inputmode="decimal" autocomplete="off" wire:model="weightChi" class="{{ $inputClass }}" placeholder="1,25" required>
            </x-field>
            <x-field label="Ngày mua" name="bought_on">
                <x-date-input id="bought_on" model="boughtOn" />
            </x-field>
        </div>
        <x-field label="Tổng tiền đã trả" name="cost_amount" hint="Kể cả tiền công. Vàng được tặng thì nhập 0.">
            <x-money-input id="cost_amount" model="costAmount" />
        </x-field>
        <x-field label="Tiệm vàng" name="shop">
            <input id="shop" type="text" wire:model="shop" class="{{ $inputClass }}">
        </x-field>
        <x-field label="Ghi chú" name="note">
            <input id="note" type="text" wire:model="note" class="{{ $inputClass }}">
        </x-field>
        <x-photo-field :saved-photos="$savedPhotos" :photos="$photos" photo-route="gold.photos.show" hint="Tối đa {{ \App\Models\GoldItem::MAX_PHOTOS }} ảnh, ví dụ ảnh món và ảnh phiếu mua. Chỉ mình bạn xem được." />
        <button type="submit" x-bind:disabled="busy" class="w-full rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white disabled:opacity-50">Lưu</button>
    </form>
</div>
