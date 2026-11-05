{{-- Dùng chung cho form dây hụi viên và chủ hụi; cần $group và $inputClass. --}}
@php $isLunar = $frequency === \App\Enums\HuiFrequency::ThangAm->value; @endphp

@unless ($group)
    <fieldset>
        <legend class="block text-sm font-medium text-gray-700">Lịch chơi</legend>
        <div class="mt-1 grid grid-cols-2 gap-2">
            @foreach (['thang' => 'Dương lịch', 'thang_am' => 'Âm lịch'] as $value => $label)
                <label @class([
                    'cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-medium',
                    'border-emerald-600 bg-emerald-50 text-emerald-800' => $frequency === $value,
                    'border-gray-300 text-gray-700' => $frequency !== $value,
                ])>
                    <input type="radio" wire:model.live="frequency" value="{{ $value }}" class="sr-only">
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </fieldset>
@endunless

@if ($isLunar && ! $group)
    <x-field label="Ngày mở kỳ 1 (âm lịch)" name="start_date" hint="Tháng nhuận cũng đóng một kỳ. Ngày 30 gặp tháng thiếu thì lùi về 29.">
        <div class="grid grid-cols-3 gap-2">
            <select id="start_date" wire:model.live="lunarDay" aria-label="Ngày âm" class="{{ $inputClass }}">
                @foreach (range(1, 30) as $day)
                    <option value="{{ $day }}">Ngày {{ $day }}</option>
                @endforeach
            </select>
            <select wire:model.live="lunarMonth" aria-label="Tháng âm" class="{{ $inputClass }}">
                @foreach ($this->lunarMonthOptions() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <input type="number" inputmode="numeric" min="1990" max="2100" wire:model.live.blur="lunarYear" aria-label="Năm âm" class="{{ $inputClass }}" required>
        </div>
        @if ($preview = $this->lunarSolarPreview())
            <p class="mt-1 text-sm text-emerald-700">Tức ngày dương {{ $preview }}</p>
        @endif
    </x-field>
@endif

<div class="grid grid-cols-2 gap-3">
    @if (! $isLunar || $group)
        <x-field label="Ngày mở kỳ 1" name="start_date" :hint="$group?->frequency->isLunar() ? $group->frequency->label().' · '.$group->lunarLabel($group->start_date) : null">
            <x-date-input id="start_date" model="startDate" :disabled="(bool) $group" required />
        </x-field>
    @endif
    <x-field label="Hạn đóng sau (ngày)" name="due_offset_days">
        <input id="due_offset_days" type="number" inputmode="numeric" min="0" step="1" wire:model="dueOffsetDays" class="{{ $inputClass }}">
    </x-field>
</div>
