@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600 disabled:bg-gray-100 disabled:text-gray-500'; @endphp

<div>
    <div class="mb-4 flex items-center gap-2">
        <a href="{{ $group ? route('host.groups.show', $group) : route('host.groups.index') }}" wire:navigate class="text-emerald-700" aria-label="Quay lại">&larr;</a>
        <h1 class="text-xl font-semibold">{{ $group ? 'Sửa dây hụi' : 'Thêm dây hụi' }}</h1>
    </div>

    <form wire:submit="save" class="space-y-4">
        <div class="rounded-xl bg-white p-4 shadow-sm space-y-4">
            <x-field label="Tên dây" name="name">
                <input id="name" type="text" wire:model="name" class="{{ $inputClass }}" placeholder="Hụi 5tr nhà mình" required>
            </x-field>

            <div class="grid grid-cols-2 gap-3">
                <x-field label="Mệnh giá" name="face_value" :hint="$group ? 'Không sửa sau khi tạo' : null">
                    <x-money-input id="face_value" model="faceValue" :disabled="(bool) $group" required />
                    @unless ($group)
                        <x-amount-suggestions model="faceValue" :amounts="$faceValueSuggestions" :selected="$faceValue" />
                    @endunless
                </x-field>
                <x-field label="Tổng số tay" name="total_slots">
                    <input id="total_slots" type="number" inputmode="numeric" min="2" max="200" step="1" wire:model.live.debounce.400ms="totalSlots" class="{{ $inputClass }}" @disabled($group) required>
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

            <x-field label="Tiền thảo mỗi lần hốt" name="commission_amount" hint="Người hốt đưa lại cho mình, trừ thẳng vào tiền chi. Để 0 nếu không có.">
                <x-money-input id="commission_amount" model="commissionAmount" />
                <x-amount-suggestions model="commissionAmount" :amounts="$commissionSuggestions" :selected="$commissionAmount" />
            </x-field>

            <x-field label="Ghi chú" name="note">
                <textarea id="note" rows="2" wire:model="note" class="{{ $inputClass }}"></textarea>
            </x-field>
        </div>

        @unless ($group)
            <div class="rounded-xl bg-white p-4 shadow-sm space-y-3">
                <div>
                    <h2 class="font-semibold">Hụi viên giữ từng tay</h2>
                    <p class="text-sm text-gray-500">Một người giữ nhiều tay thì chọn tên họ ở nhiều dòng.</p>
                </div>

                <div class="flex gap-2">
                    <input type="text" wire:model="newMemberName" wire:keydown.enter.prevent="addMember" placeholder="Tên hụi viên mới" aria-label="Tên hụi viên mới" class="{{ $inputClass }} flex-1">
                    <button type="button" wire:click="addMember" class="shrink-0 rounded-lg border border-emerald-600 px-3 text-sm font-semibold text-emerald-700">Thêm</button>
                </div>
                @error('newMemberName') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @if ($memberIds === [])
                    <p class="text-sm text-gray-500">Nhập tổng số tay để chọn hụi viên.</p>
                @endif

                <div class="space-y-2">
                    @foreach ($memberIds as $index => $memberId)
                        <div wire:key="slot-{{ $index }}">
                            <div class="flex items-center gap-2">
                                <span class="w-14 shrink-0 text-sm text-gray-600">Tay {{ $index + 1 }}</span>
                                <select wire:model.live="memberIds.{{ $index }}" aria-label="Hụi viên giữ tay {{ $index + 1 }}" class="{{ $inputClass }}">
                                    <option value="">Chọn hụi viên</option>
                                    @foreach ($members as $member)
                                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                                    @endforeach
                                </select>
                                @if ($memberId !== '' && in_array('', $memberIds, true))
                                    <button type="button" wire:click="fillEmptySlots('{{ $memberId }}')" class="shrink-0 text-xs text-emerald-700" title="Gán người này cho mọi tay còn trống">Điền tay trống</button>
                                @endif
                            </div>
                            @error('member_ids.'.$index) <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
                @error('member_ids') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
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
