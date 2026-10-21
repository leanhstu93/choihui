@php
    $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600';
    $statusClasses = [
        'chua' => 'bg-orange-50 text-orange-700',
        'du' => 'bg-emerald-50 text-emerald-700',
        'thieu' => 'bg-red-50 text-red-700',
        'muon' => 'bg-amber-50 text-amber-700',
        'con_no' => 'bg-red-50 text-red-700',
        'bung' => 'bg-gray-200 text-gray-700',
    ];
@endphp

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('groups.show', $group) }}" wire:navigate class="text-emerald-700" aria-label="Quay lại">&larr;</a>
        <div>
            <h1 class="text-xl font-semibold">Kỳ {{ $round->round_no }}/{{ $group->total_slots }}</h1>
            <p class="text-sm text-gray-500">{{ $group->name }} · mở @date($round->open_date) · hạn đóng {{ $dueDate->format('d/m/Y') }}</p>
        </div>
    </div>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Kết quả kỳ</h2>
        <form wire:submit="saveResult" class="mt-3 space-y-4">
            <x-field label="Lãi thăm" name="bid_amount" hint="Kỳ đầu chủ hụi hốt không lãi thì nhập 0.">
                <x-money-input id="bid_amount" model="bidAmount" :live="true" />
                <x-amount-suggestions model="bidAmount" :amounts="$bidSuggestions" :selected="$bidAmount" />
            </x-field>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">Người hốt</legend>
                <div class="mt-2 grid grid-cols-3 gap-2 text-sm">
                    @foreach (\App\Enums\WinnerType::cases() as $type)
                        <label @class(['rounded-lg border px-3 py-2 text-center cursor-pointer', 'border-emerald-600 bg-emerald-50 font-semibold' => $winnerType === $type->value, 'border-gray-300' => $winnerType !== $type->value])>
                            <input type="radio" wire:model.live="winnerType" value="{{ $type->value }}" class="sr-only">
                            {{ $type->label() }}
                        </label>
                    @endforeach
                </div>
                @error('winner_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </fieldset>

            @if ($winnerType === 'minh')
                <x-field label="Tay hốt" name="winner_slot_id">
                    <select id="winner_slot_id" wire:model="winnerSlotId" class="{{ $inputClass }}">
                        <option value="">Chọn tay</option>
                        @foreach ($availableSlots as $slot)
                            <option value="{{ $slot->id }}">{{ $slot->label }}</option>
                        @endforeach
                    </select>
                </x-field>
                @if ($winPreview !== null)
                    <div class="space-y-1 rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800">
                        <p>Dự kiến nhận: <strong>@money($winPreview['payout'])</strong>@if ($group->commission_amount > 0) (đã trừ thảo @money($group->commission_amount))@endif</p>
                        <p>Lời / lỗ dự kiến: <strong @class(['text-emerald-700' => $winPreview['profit'] >= 0, 'text-red-700' => $winPreview['profit'] < 0])>@money($winPreview['profit'])</strong></p>
                    </div>
                @endif
            @elseif ($winnerType === 'nguoi_khac')
                <x-field label="Tên người hốt (không bắt buộc)" name="winner_name">
                    <input id="winner_name" type="text" wire:model="winnerName" class="{{ $inputClass }}">
                </x-field>
            @endif

            @if ($confirmationMessage)
                <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <p>{{ $confirmationMessage }}</p>
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="saveResult(true)" class="rounded-lg bg-amber-600 px-3 py-2 font-semibold text-white">Vẫn cập nhật</button>
                        <button type="button" wire:click="$set('confirmationMessage', null)" class="rounded-lg border border-amber-300 px-3 py-2">Hủy</button>
                    </div>
                </div>
            @endif

            <button type="submit" class="w-full rounded-lg bg-emerald-700 py-3 font-semibold text-white" wire:loading.attr="disabled">Lưu kết quả</button>
        </form>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Khoản đóng / nhận của mình</h2>

        @if ($round->cashEntries->isEmpty())
            <p class="mt-2 text-sm text-gray-500">Nhập lãi thăm để tính số tiền phải đóng.</p>
        @endif

        <ul class="mt-2 divide-y divide-gray-100">
            @foreach ($round->cashEntries->sortBy('slot.label') as $entry)
                <li class="py-3" wire:key="entry-{{ $entry->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="text-sm">
                            <div class="font-medium">{{ $entry->slot->label }} · {{ $entry->direction->label() }} @money($entry->amount_expected)</div>
                            @if ($entry->amount_actual > 0)
                                <div class="text-gray-500">Thực tế @money($entry->amount_actual) ngày @date($entry->paid_date)</div>
                            @endif
                            @if ($entry->note)
                                <div class="text-gray-500">{{ $entry->note }}</div>
                            @endif
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClasses[$entry->status->value] }}">{{ $entry->status->label() }}</span>
                    </div>

                    @if ($editingEntryId === $entry->id)
                        <form wire:submit="saveEntry" class="mt-3 space-y-3 rounded-lg bg-gray-50 p-3">
                            <div class="grid grid-cols-2 gap-2">
                                <x-field label="Số tiền" name="amount_actual">
                                    <x-money-input id="amount_actual" model="payAmount" />
                                </x-field>
                                <x-field label="Ngày" name="paid_date">
                                    <input id="paid_date" type="date" wire:model="payDate" class="{{ $inputClass }}">
                                </x-field>
                            </div>
                            <x-field label="Ghi chú" name="note">
                                <input id="note" type="text" wire:model="payNote" class="{{ $inputClass }}">
                            </x-field>
                            @if ($entry->direction === \App\Enums\CashDirection::Nhan)
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="writtenOff" class="rounded border-gray-300 text-emerald-700">
                                    Phần còn thiếu không đòi được (bùng)
                                </label>
                            @endif
                            <div class="flex gap-2">
                                <button type="submit" class="flex-1 rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white">Lưu</button>
                                <button type="button" wire:click="cancelEdit" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">Hủy</button>
                            </div>
                        </form>
                    @else
                        <div class="mt-2 flex gap-2 text-sm">
                            @if ($entry->status->value === 'chua')
                                <button type="button" wire:click="settleInFull('{{ $entry->id }}')" class="flex-1 rounded-lg bg-emerald-700 py-2 font-semibold text-white">
                                    {{ $entry->direction === \App\Enums\CashDirection::Nhan ? 'Đã nhận đủ' : 'Đã đóng đủ' }}
                                </button>
                            @endif
                            <button type="button" wire:click="editEntry('{{ $entry->id }}')" class="rounded-lg border border-gray-300 px-3 py-2">
                                {{ $entry->status->value === 'chua' ? 'Nhập số khác' : 'Sửa' }}
                            </button>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</div>
