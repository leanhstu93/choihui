@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600'; @endphp

<div class="space-y-4">
    <a href="{{ route('family.health.show', $personId) }}" wire:navigate class="text-sm text-emerald-700">‹ {{ $person->name }}</a>
    <h1 class="text-xl font-semibold">{{ $recordId ? 'Sửa lần khám' : 'Thêm lần khám' }}</h1>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif

    <form wire:submit="save" x-data="photoPicker" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <div class="grid grid-cols-2 gap-3">
            <x-field label="Ngày" name="visited_on">
                <x-date-input id="visited_on" model="visitedOn" />
            </x-field>
            <x-field label="Loại" name="kind">
                <select id="kind" wire:model="kind" class="{{ $inputClass }}">
                    @foreach ($kinds as $kindOption)
                        <option value="{{ $kindOption->value }}">{{ $kindOption->label() }}</option>
                    @endforeach
                </select>
            </x-field>
        </div>
        <x-field label="Nội dung" name="title">
            <input id="title" type="text" wire:model="title" class="{{ $inputClass }}" placeholder="Khám tai mũi họng, Vắc xin cúm, Xét nghiệm máu..." required>
        </x-field>
        <div class="grid grid-cols-2 gap-3">
            <x-field label="Nơi khám" name="place">
                <input id="place" type="text" wire:model="place" class="{{ $inputClass }}">
            </x-field>
            <x-field label="Chi phí" name="amount" hint="Không bắt buộc.">
                <x-money-input id="amount" model="amount" />
            </x-field>
        </div>
        <x-field label="Hẹn tái khám" name="follow_up_on" hint="Bỏ trống nếu không có. App sẽ nhắc trước 1 ngày.">
            <x-date-input id="follow_up_on" model="followUpOn" />
        </x-field>
        <x-field label="Ghi chú" name="note" hint="Chẩn đoán, thuốc, lời dặn của bác sĩ...">
            <textarea id="note" rows="3" wire:model="note" class="{{ $inputClass }}"></textarea>
        </x-field>
        <x-photo-field label="Ảnh đơn thuốc, kết quả" :saved-photos="$savedPhotos" :photos="$photos" photo-route="family.health.photos.show" hint="Tối đa {{ \App\Models\HealthRecord::MAX_PHOTOS }} ảnh. Chỉ mình bạn xem được." />
        <button type="submit" x-bind:disabled="busy" class="w-full rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white disabled:opacity-50">Lưu</button>
    </form>

    @if ($recordId)
        <button type="button" wire:click="deleteRecord" wire:confirm="Xóa lần khám này và ảnh của nó?" class="w-full text-center text-sm text-red-600">Xóa lần khám này</button>
    @endif
</div>
