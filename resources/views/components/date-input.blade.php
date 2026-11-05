@props(['model', 'disabled' => false])

{{-- Ô ngày luôn hiện dd/mm/yyyy, không phụ thuộc ngôn ngữ trình duyệt; Livewire nhận Y-m-d. --}}
<div x-data="dateInput($wire.entangle('{{ $model }}', false))" class="relative">
    <input type="text" inputmode="numeric" autocomplete="off" maxlength="10" placeholder="dd/mm/yyyy" x-bind:value="display" x-on:input="onInput($event)" @disabled($disabled)
        {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 pr-9 focus:border-emerald-600 focus:ring-emerald-600 disabled:bg-gray-100 disabled:text-gray-500']) }}>
    @unless ($disabled)
        <input type="date" x-ref="picker" x-bind:value="pickerValue" x-on:change="onPick($event)" tabindex="-1" aria-hidden="true" class="pointer-events-none absolute bottom-0 right-0 h-0 w-0 opacity-0">
        <button type="button" x-on:click="openPicker()" class="absolute inset-y-0 right-0 flex w-9 items-center justify-center text-gray-400 hover:text-emerald-700" aria-label="Chọn ngày trên lịch">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="5" width="18" height="16" rx="2" />
                <path d="M3 10h18M8 3v4M16 3v4" />
            </svg>
        </button>
    @endunless
</div>
