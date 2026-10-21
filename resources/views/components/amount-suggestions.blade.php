@props(['model', 'amounts', 'selected' => null])

@if ($amounts !== [])
    <div class="mt-2 flex flex-wrap gap-2">
        @foreach ($amounts as $amount)
            <button type="button" wire:click="$set('{{ $model }}', {{ $amount }})" wire:key="{{ $model }}-{{ $amount }}"
                @class(['rounded-full border px-3 py-1 text-sm', 'border-emerald-600 bg-emerald-50 font-semibold text-emerald-800' => $selected === $amount, 'border-gray-300 text-gray-700' => $selected !== $amount])>@money($amount)</button>
        @endforeach
    </div>
@endif
