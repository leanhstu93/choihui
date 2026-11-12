@php
    $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600';
    $chipClass = fn (bool $selected) => $selected
        ? 'border-emerald-600 bg-emerald-50 font-semibold text-emerald-800 ring-1 ring-emerald-600'
        : 'border-gray-200 bg-white text-gray-700 hover:border-emerald-300';
    $remindValue = (int) $remindDaysBefore;
    $remindIsPreset = array_key_exists($remindValue, $remindPresets) && trim($remindDaysBefore) !== '';
@endphp

<div class="mx-auto max-w-xl space-y-4">
    <a href="{{ route('family.index') }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-emerald-700">‹ Ngày nhớ</a>
    <h1 class="text-xl font-semibold">{{ $eventId ? 'Sửa ngày nhớ' : 'Thêm ngày nhớ' }}</h1>

    <form wire:submit="save" class="space-y-5 rounded-2xl bg-white p-4 shadow-sm sm:p-5">
        {{-- Loại: nút lớn có biểu tượng --}}
        <fieldset>
            <legend class="block text-sm font-medium text-gray-700">Đây là ngày gì?</legend>
            <div class="mt-2 grid grid-cols-4 gap-2">
                @foreach ($kinds as $kindOption)
                    <label class="flex cursor-pointer flex-col items-center gap-1 rounded-xl border px-1 py-3 text-sm transition {{ $chipClass($kind === $kindOption->value) }}">
                        <input type="radio" wire:model.live="kind" value="{{ $kindOption->value }}" class="sr-only">
                        <span class="text-2xl" aria-hidden="true">{{ $kindOption->icon() }}</span>
                        {{ $kindOption->label() }}
                    </label>
                @endforeach
            </div>
            @error('kind')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </fieldset>

        <x-field label="Tên" name="title">
            <input id="title" type="text" wire:model="title" placeholder="{{ $selectedKind->titlePlaceholder() }}" class="{{ $inputClass }}" required>
        </x-field>

        {{-- Âm / dương lịch --}}
        <fieldset>
            <legend class="block text-sm font-medium text-gray-700">Tính theo</legend>
            <div class="mt-2 grid grid-cols-2 gap-2">
                @foreach (\App\Enums\CalendarType::cases() as $calendarOption)
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm transition {{ $chipClass($calendar === $calendarOption->value) }}">
                        <input type="radio" wire:model.live="calendar" value="{{ $calendarOption->value }}" class="sr-only">
                        <span aria-hidden="true">{{ $calendarOption->isLunar() ? '🌙' : '☀️' }}</span> {{ $calendarOption->label() }}
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-gray-500">{{ $lunar ? 'Mỗi năm app tự đổi ngày âm sang ngày dương.' : 'Lặp lại đúng ngày này mỗi năm.' }}</p>
            @error('calendar')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </fieldset>

        <div class="grid grid-cols-2 gap-3">
            <x-field :label="$lunar ? 'Ngày âm' : 'Ngày'" name="day">
                <select id="day" wire:model.live="day" class="{{ $inputClass }}" required>
                    <option value="">Chọn ngày</option>
                    @for ($dayOption = 1; $dayOption <= $maxDay; $dayOption++)
                        <option value="{{ $dayOption }}">{{ $lunar && $dayOption <= 10 ? 'Mùng '.$dayOption : ($lunar && $dayOption === 15 ? '15 (Rằm)' : $dayOption) }}</option>
                    @endfor
                </select>
            </x-field>
            <x-field :label="$lunar ? 'Tháng âm' : 'Tháng'" name="month">
                <select id="month" wire:model.live="month" class="{{ $inputClass }}" required>
                    <option value="">Chọn tháng</option>
                    @for ($monthOption = 1; $monthOption <= 12; $monthOption++)
                        <option value="{{ $monthOption }}">Tháng {{ $monthOption }}{{ $lunar && $monthOption === 1 ? ' (Giêng)' : '' }}{{ $lunar && $monthOption === 12 ? ' (Chạp)' : '' }}</option>
                    @endfor
                </select>
            </x-field>
        </div>

        @if ($preview)
            <div class="flex items-center gap-3 rounded-xl bg-emerald-50 p-3 text-emerald-900" role="status">
                <span class="text-2xl" aria-hidden="true">📅</span>
                <div class="text-sm">
                    <div class="text-emerald-700">Lần tới rơi vào</div>
                    <div class="text-base font-semibold">@weekday($preview->date), @date($preview->date)</div>
                    <div>({{ $preview->lunarLabel }}) · {{ $preview->countdown() }}{{ $preview->yearsLabel ? ' · '.$preview->yearsLabel : '' }}</div>
                </div>
            </div>
        @endif

        <x-field :label="$selectedKind->originYearLabel().($lunar ? ' (năm âm lịch)' : '')" name="origin_year" :hint="match ($selectedKind) {
            \App\Enums\FamilyEventKind::Gio => 'Để app đếm giỗ đầu, giỗ lần thứ mấy. Không bắt buộc.',
            \App\Enums\FamilyEventKind::SinhNhat => 'Để app tính tròn mấy tuổi. Không bắt buộc.',
            default => 'Để app đếm được bao nhiêu năm. Không bắt buộc.',
        }">
            <input id="origin_year" type="text" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="Ví dụ: 2015" wire:model.live.debounce.400ms="originYear" class="{{ $inputClass }} sm:w-40">
        </x-field>

        {{-- Nhắc trước: chọn nhanh, hoặc nhập số ngày khác --}}
        <fieldset x-data="{ custom: @js(! $remindIsPreset) }">
            <legend class="block text-sm font-medium text-gray-700">Nhắc qua email</legend>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($remindPresets as $presetDays => $presetLabel)
                    <button type="button" wire:click="$set('remindDaysBefore', '{{ $presetDays }}')" @click="custom = false" class="rounded-full border px-3 py-1.5 text-sm transition {{ $chipClass($remindIsPreset && $remindValue === $presetDays) }}">{{ $presetLabel }}</button>
                @endforeach
                <button type="button" @click="custom = true; $nextTick(() => $refs.remind.focus())" :class="custom ? @js($chipClass(true)) : @js($chipClass(false))" class="rounded-full border px-3 py-1.5 text-sm transition">Khác…</button>
            </div>
            <div x-show="custom" x-cloak class="mt-2 flex items-center gap-2 text-sm">
                Trước
                <input id="remind_days_before" x-ref="remind" type="number" min="0" max="30" wire:model.live.debounce.400ms="remindDaysBefore" class="w-20 rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600">
                ngày (0–30)
            </div>
            <p class="mt-1 text-xs text-gray-500">
                @if ($remindValue > 0)
                    Email lúc 7 giờ sáng, trước {{ $remindValue }} ngày và thêm một lần đúng ngày.
                @else
                    Không gửi email cho ngày này. Vẫn hiện trong danh sách.
                @endif
            </p>
            @error('remind_days_before')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </fieldset>

        <x-field label="Ghi chú" name="note" hint="Không bắt buộc.">
            <textarea id="note" rows="2" wire:model="note" placeholder="Ví dụ: làm mâm cơm, mời cô chú bên nội" class="{{ $inputClass }}"></textarea>
        </x-field>

        <button type="submit" class="w-full rounded-xl bg-emerald-700 py-3 font-semibold text-white shadow-sm hover:bg-emerald-800" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="save">{{ $eventId ? 'Lưu thay đổi' : 'Lưu ngày nhớ' }}</span>
            <span wire:loading wire:target="save">Đang lưu…</span>
        </button>
    </form>

    @if ($eventId)
        <button type="button" wire:click="delete" wire:confirm="Xóa ngày nhớ này? Không hoàn tác được." class="w-full rounded-xl border border-red-200 py-2.5 text-center text-sm font-medium text-red-600 hover:bg-red-50">Xóa ngày nhớ này</button>
    @endif
</div>
