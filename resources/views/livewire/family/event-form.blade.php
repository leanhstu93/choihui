@php $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600'; @endphp

<div class="space-y-4">
    <a href="{{ route('family.index') }}" wire:navigate class="text-sm text-emerald-700">‹ Ngày nhớ</a>
    <h1 class="text-xl font-semibold">{{ $eventId ? 'Sửa ngày nhớ' : 'Thêm ngày nhớ' }}</h1>

    <form wire:submit="save" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
        <x-field label="Tên" name="title" hint="Ví dụ: Giỗ ông nội, Sinh nhật mẹ, Kỷ niệm ngày cưới">
            <input id="title" type="text" wire:model="title" class="{{ $inputClass }}" required>
        </x-field>

        <x-field label="Loại" name="kind">
            <select id="kind" wire:model.live="kind" class="{{ $inputClass }}">
                @foreach ($kinds as $kindOption)
                    <option value="{{ $kindOption->value }}">{{ $kindOption->label() }}</option>
                @endforeach
            </select>
        </x-field>

        <fieldset>
            <legend class="block text-sm font-medium text-gray-700">Tính theo</legend>
            <div class="mt-1 grid grid-cols-2 gap-2">
                @foreach (\App\Enums\CalendarType::cases() as $calendarOption)
                    <label @class(['flex items-center justify-center gap-2 rounded-lg border px-3 py-2 text-sm', 'border-emerald-600 bg-emerald-50 font-semibold text-emerald-800' => $calendar === $calendarOption->value, 'border-gray-300' => $calendar !== $calendarOption->value])>
                        <input type="radio" wire:model.live="calendar" value="{{ $calendarOption->value }}" class="sr-only">
                        {{ $calendarOption->label() }}
                    </label>
                @endforeach
            </div>
            @error('calendar')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="grid grid-cols-2 gap-3">
            <x-field label="Ngày" name="day">
                <select id="day" wire:model.live="day" class="{{ $inputClass }}" required>
                    <option value="">Chọn</option>
                    @for ($dayOption = 1; $dayOption <= $maxDay; $dayOption++)
                        <option value="{{ $dayOption }}">{{ $dayOption }}</option>
                    @endfor
                </select>
            </x-field>
            <x-field label="Tháng" name="month">
                <select id="month" wire:model.live="month" class="{{ $inputClass }}" required>
                    <option value="">Chọn</option>
                    @for ($monthOption = 1; $monthOption <= 12; $monthOption++)
                        <option value="{{ $monthOption }}">Tháng {{ $monthOption }}{{ $calendar === 'am' && $monthOption === 1 ? ' (Giêng)' : '' }}{{ $calendar === 'am' && $monthOption === 12 ? ' (Chạp)' : '' }}</option>
                    @endfor
                </select>
            </x-field>
        </div>

        @if ($preview)
            <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800" role="status">
                Lần tới: <strong>@date($preview->date)</strong> ({{ $preview->lunarLabel }}) · {{ $preview->countdown() }}{{ $preview->yearsLabel ? ' · '.$preview->yearsLabel : '' }}
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3">
            <x-field label="Năm gốc" name="origin_year" :hint="$calendar === 'am' ? 'Năm âm lịch: năm sinh, năm mất... Không bắt buộc.' : 'Năm sinh, năm cưới... Không bắt buộc.'">
                <input id="origin_year" type="text" inputmode="numeric" maxlength="4" autocomplete="off" wire:model.live.debounce.400ms="originYear" class="{{ $inputClass }}">
            </x-field>
            <x-field label="Nhắc trước (ngày)" name="remind_days_before" hint="0 là không nhắc.">
                <input id="remind_days_before" type="number" min="0" max="30" wire:model="remindDaysBefore" class="{{ $inputClass }}">
            </x-field>
        </div>

        <x-field label="Ghi chú" name="note">
            <input id="note" type="text" wire:model="note" class="{{ $inputClass }}">
        </x-field>

        <button type="submit" class="w-full rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white">Lưu</button>
    </form>

    @if ($eventId)
        <button type="button" wire:click="delete" wire:confirm="Xóa ngày nhớ này?" class="w-full text-center text-sm text-red-600">Xóa ngày nhớ này</button>
    @endif
</div>
