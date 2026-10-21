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
    $isPayout = fn ($entry) => $entry->direction === \App\Enums\HostDirection::Chi;
@endphp

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('host.groups.show', $group) }}" wire:navigate class="text-emerald-700" aria-label="Quay lại">&larr;</a>
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
            <x-field label="Lãi thăm" name="bid_amount" hint="Kỳ không có lãi thì nhập 0.">
                <x-money-input id="bid_amount" model="bidAmount" :live="true" />
                <x-amount-suggestions model="bidAmount" :amounts="$bidSuggestions" :selected="$bidAmount" />
            </x-field>

            <x-field label="Tay hốt" name="winner_host_slot_id">
                <select id="winner_host_slot_id" wire:model.live="winnerSlotId" class="{{ $inputClass }}">
                    <option value="">Chưa có</option>
                    @foreach ($availableSlots as $slot)
                        <option value="{{ $slot->id }}">Tay {{ $slot->slot_no }} · {{ $slot->member->name }}</option>
                    @endforeach
                </select>
            </x-field>

            @if ($preview !== null)
                <div class="space-y-1 rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800">
                    <p>Phải thu {{ $group->total_slots - 1 }} tay: <strong>@money($preview['collection'])</strong></p>
                    <p>Chi cho người hốt: <strong>@money($preview['payout'])</strong>@if ($preview['commission'] > 0) (đã trừ thảo @money($preview['commission']))@endif</p>
                </div>
            @endif

            @if ($confirmationMessage)
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                    <p>{{ $confirmationMessage }}</p>
                    <div class="mt-2 flex gap-2">
                        <button type="button" wire:click="saveResult(true)" class="rounded-lg bg-amber-600 px-3 py-2 font-semibold text-white">Vẫn cập nhật</button>
                        <button type="button" wire:click="$set('confirmationMessage', null)" class="rounded-lg border border-amber-300 px-3 py-2">Hủy</button>
                    </div>
                </div>
            @endif

            <button type="submit" class="w-full rounded-lg bg-emerald-700 py-3 font-semibold text-white" wire:loading.attr="disabled">Lưu kết quả</button>
        </form>
    </section>

    @if ($payout !== null)
        <div class="grid grid-cols-3 gap-2 text-center">
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã thu</div><div class="font-semibold">@money($totals['collected'])</div><div class="text-xs text-gray-400">/ @money($totals['expected'])</div></div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã chi</div><div class="font-semibold">@money($totals['paidOut'])</div><div class="text-xs text-gray-400">/ @money($payout->amount_expected)</div></div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Mình bù</div><div @class(['font-semibold', 'text-red-700' => $totals['topUp'] > 0])>@money($totals['topUp'])</div></div>
        </div>
    @endif

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <h2 class="font-semibold">Thu / chi</h2>
            @if ($totals['uncollectedCount'] > 0)
                <button type="button" wire:click="collectAll" wire:confirm="Ghi đã thu đủ {{ $totals['uncollectedCount'] }} tay chưa đóng, ngày hôm nay?" class="rounded-lg border border-emerald-600 px-3 py-1 text-sm font-semibold text-emerald-700">Thu đủ tất cả</button>
            @endif
        </div>

        @if ($payout === null)
            <p class="mt-2 text-sm text-gray-500">Nhập lãi thăm và tay hốt để tính các khoản thu / chi.</p>
        @endif

        <ul class="mt-2 divide-y divide-gray-100">
            @foreach (collect([$payout])->filter()->concat($collections) as $entry)
                <li class="py-3" wire:key="entry-{{ $entry->id }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="text-sm">
                            <div class="font-medium">
                                Tay {{ $entry->hostSlot->slot_no }} · {{ $entry->hostSlot->member->name }}
                                <span @class(['text-sky-700' => $isPayout($entry), 'text-gray-600' => ! $isPayout($entry)])>· {{ $isPayout($entry) ? 'Chi' : 'Thu' }} @money($entry->amount_expected)</span>
                            </div>
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
                            @unless ($isPayout($entry))
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" wire:model="writtenOff" class="rounded border-gray-300 text-emerald-700">
                                    Phần còn thiếu không đòi được (bùng)
                                </label>
                            @endunless
                            <div class="flex gap-2">
                                <button type="submit" class="flex-1 rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white">Lưu</button>
                                <button type="button" wire:click="cancelEdit" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">Hủy</button>
                            </div>
                        </form>
                    @else
                        <div class="mt-2 flex gap-2 text-sm">
                            @if ($entry->status->value === 'chua')
                                <button type="button" wire:click="settleInFull('{{ $entry->id }}')" @class(['flex-1 rounded-lg py-2 font-semibold text-white', 'bg-sky-700' => $isPayout($entry), 'bg-emerald-700' => ! $isPayout($entry)])>
                                    {{ $isPayout($entry) ? 'Đã chi đủ' : 'Đã thu đủ' }}
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
