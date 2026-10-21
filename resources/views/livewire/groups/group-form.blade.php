@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 disabled:bg-gray-100 disabled:text-gray-500'; @endphp

<div>
    <div class="mb-4 flex items-center gap-2">
        <a href="{{ $group ? route('groups.show', $group) : route('groups.index') }}" wire:navigate class="text-emerald-700" aria-label="Quay lại">&larr;</a>
        <h1 class="text-xl font-semibold">{{ $group ? 'Sửa dây hụi' : 'Thêm dây hụi' }}</h1>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="rounded-xl bg-white p-4 shadow-sm space-y-4">
            <x-field label="Tên dây" name="name">
                <input id="name" type="text" wire:model="name" class="{{ $inputClass }}" placeholder="Hụi 5tr cô Hoa" required>
            </x-field>

            <div class="grid grid-cols-2 gap-3">
                <x-field label="Mệnh giá" name="face_value" :hint="$group ? 'Không sửa sau khi tạo' : null">
                    <x-money-input id="face_value" model="faceValue" :disabled="(bool) $group" required />
                </x-field>
                <x-field label="Tổng số tay" name="total_slots">
                    <input id="total_slots" type="number" inputmode="numeric" min="2" step="1" wire:model="totalSlots" class="{{ $inputClass }}" @disabled($group) required>
                </x-field>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <x-field label="Ngày mở kỳ 1" name="start_date">
                    <input id="start_date" type="date" wire:model="startDate" class="{{ $inputClass }}" @disabled($group) required>
                </x-field>
                <x-field label="Hạn đóng sau (ngày)" name="due_offset_days">
                    <input id="due_offset_days" type="number" inputmode="numeric" min="0" step="1" wire:model="dueOffsetDays" class="{{ $inputClass }}">
                </x-field>
            </div>

            <x-field label="Tiền thảo mỗi lần hốt" name="commission_amount" hint="Số tiền đưa chủ hụi khi tay của mình hốt. Để 0 nếu không có.">
                <x-money-input id="commission_amount" model="commissionAmount" />
            </x-field>
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Chủ hụi" name="host_name">
                    <input id="host_name" type="text" wire:model="hostName" class="{{ $inputClass }}" required>
                </x-field>
                <x-field label="SĐT chủ hụi" name="host_phone">
                    <input id="host_phone" type="tel" wire:model="hostPhone" class="{{ $inputClass }}">
                </x-field>
            </div>
            <x-field label="Ghi chú" name="note">
                <textarea id="note" rows="2" wire:model="note" class="{{ $inputClass }}"></textarea>
            </x-field>
        </div>

        @unless ($group)
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Các tay của mình</span>
                    <button type="button" wire:click="addSlotLabel" class="text-sm font-semibold text-emerald-700">+ Thêm tay</button>
                </div>
                <div class="mt-2 space-y-2">
                    @foreach ($slotLabels as $index => $label)
                        <div wire:key="slot-label-{{ $index }}">
                            <div class="flex gap-2">
                                <input type="text" wire:model="slotLabels.{{ $index }}" aria-label="Tên tay {{ $index + 1 }}" class="{{ $inputClass }}">
                                @if (count($slotLabels) > 1)
                                    <button type="button" wire:click="removeSlotLabel({{ $index }})" class="px-2 text-gray-400 hover:text-red-600" aria-label="Xóa tay {{ $index + 1 }}">&times;</button>
                                @endif
                            </div>
                            @error('slots.'.$index) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
                @error('slots') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        @endunless

        @if ($confirmationMessage)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                <p>{{ $confirmationMessage }}</p>
                <div class="mt-3 flex gap-2">
                    <button type="button" wire:click="save(true)" class="rounded-lg bg-amber-600 px-3 py-2 font-semibold text-white">Vẫn cập nhật</button>
                    <button type="button" wire:click="$set('confirmationMessage', null)" class="rounded-lg border border-amber-300 px-3 py-2">Hủy</button>
                </div>
            </div>
        @endif

        <button type="submit" class="w-full rounded-lg bg-emerald-700 py-3 font-semibold text-white disabled:opacity-60" wire:loading.attr="disabled">
            {{ $group ? 'Lưu thay đổi' : 'Tạo dây hụi' }}
        </button>
    </form>
</div>
