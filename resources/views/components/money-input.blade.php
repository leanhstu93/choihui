@props(['model', 'live' => false])

<div x-data="moneyInput($wire.entangle('{{ $model }}', {{ $live ? 'true' : 'false' }}))" class="relative">
    <input type="text" inputmode="numeric" autocomplete="off" x-bind:value="display" x-on:input="onInput($event)"
        {{ $attributes->merge(['class' => 'w-full rounded-lg border-gray-300 pr-8 focus:border-emerald-600 focus:ring-emerald-600 disabled:bg-gray-100 disabled:text-gray-500']) }}>
    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-gray-400">đ</span>
</div>
