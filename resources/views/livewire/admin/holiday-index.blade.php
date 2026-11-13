@php
    $weekday = fn (\DateTimeImmutable $date): string => ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'][(int) $date->format('w')];
    $day = fn (\DateTimeImmutable $date): string => $weekday($date).' '.$date->format('d/m/Y');
@endphp

<div>
    <h1 class="mb-4 text-xl font-semibold">Quản trị</h1>
    <x-admin-tabs />

    <div class="mb-4 rounded-2xl bg-sky-50 p-4 text-sm text-sky-900">
        <p><b>Lịch nghỉ lễ</b> hiện trên lịch âm công khai (/lich-am và trang giới thiệu). Đợt chưa nhập được app tự tính theo Bộ luật Lao động và ghi là <i>Dự kiến</i>.</p>
        <p class="mt-1">Khi Bộ Nội vụ công bố (thường khoảng tháng 9–10), bấm <b>Nhập lịch chính thức</b>, kiểm tra ngày rồi lưu. Form đã điền sẵn lịch dự kiến.</p>
    </div>

    <div class="mb-4 flex gap-2">
        @foreach ($years as $optionYear)
            <button type="button" wire:click="selectYear({{ $optionYear }})" @class(['rounded-full px-4 py-1.5 text-sm font-semibold border', 'bg-emerald-700 border-emerald-700 text-white' => $optionYear === $year, 'bg-white border-gray-300 text-gray-700' => $optionYear !== $year])>Năm {{ $optionYear }}</button>
        @endforeach
    </div>

    <div class="space-y-3">
        @foreach ($breaks as $break)
            <div wire:key="break-{{ $year }}-{{ $break->kind->value }}" class="rounded-2xl bg-white p-4 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-semibold">{{ $break->kind->label() }}</h2>
                            @if ($break->isOfficial)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Chính thức</span>
                            @else
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Dự kiến</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-700">{{ $day($break->start) }} – {{ $day($break->end) }} · <b>{{ $break->length() }} ngày</b></p>
                        @php $extra = array_filter($break->days, fn (string $reason): bool => in_array($reason, [\App\Domain\HolidayBreak::DAY_COMPENSATORY, \App\Domain\HolidayBreak::DAY_SWAPPED], true)); @endphp
                        @if ($extra !== [])
                            <p class="text-xs text-gray-500">
                                @foreach ($extra as $date => $reason)
                                    {{ \App\Domain\HolidayBreak::dayLabel($reason) }} {{ \Carbon\CarbonImmutable::parse($date)->format('d/m') }}@if (! $loop->last), @endif
                                @endforeach
                            </p>
                        @endif
                        @if ($break->makeUpOn)
                            <p class="text-xs text-gray-500">Đi làm bù {{ $day($break->makeUpOn) }}</p>
                        @endif
                        @if ($break->source)
                            <p class="text-xs text-gray-400">Nguồn: {{ $break->source }}</p>
                        @endif
                    </div>
                    @if ($editing !== $break->kind->value)
                        <div class="flex shrink-0 gap-2">
                            <button type="button" wire:click="edit('{{ $break->kind->value }}')" class="rounded-full bg-emerald-700 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-800">{{ $break->isOfficial ? 'Sửa' : 'Nhập lịch chính thức' }}</button>
                            @if ($break->isOfficial)
                                <button type="button" wire:click="revert('{{ $break->kind->value }}')" wire:confirm="Bỏ lịch chính thức, quay về lịch dự kiến?" class="rounded-full border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">Về dự kiến</button>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($editing === $break->kind->value)
                    <form wire:submit="save" class="mt-4 grid gap-3 border-t border-gray-100 pt-4 sm:grid-cols-2">
                        <label class="block text-sm">
                            <span class="text-gray-600">Nghỉ từ ngày</span>
                            <input type="date" wire:model="form.starts_on" class="mt-1 block w-full rounded-lg border-gray-300">
                            <x-input-error :messages="$errors->get('starts_on')" class="mt-1" />
                        </label>
                        <label class="block text-sm">
                            <span class="text-gray-600">Đến hết ngày</span>
                            <input type="date" wire:model="form.ends_on" class="mt-1 block w-full rounded-lg border-gray-300">
                            <x-input-error :messages="$errors->get('ends_on')" class="mt-1" />
                        </label>
                        <label class="block text-sm">
                            <span class="text-gray-600">Ngày đi làm bù <span class="text-gray-400">(nếu có)</span></span>
                            <input type="date" wire:model="form.make_up_on" class="mt-1 block w-full rounded-lg border-gray-300">
                            <x-input-error :messages="$errors->get('make_up_on')" class="mt-1" />
                        </label>
                        <label class="block text-sm">
                            <span class="text-gray-600">Nguồn</span>
                            <input type="text" wire:model="form.source" placeholder="Thông báo số …/TB-BNV ngày …" class="mt-1 block w-full rounded-lg border-gray-300">
                            <x-input-error :messages="$errors->get('source')" class="mt-1" />
                        </label>
                        <div class="flex gap-2 sm:col-span-2">
                            <button type="submit" class="rounded-full bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Lưu lịch chính thức</button>
                            <button type="button" wire:click="cancel" class="rounded-full border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700">Hủy</button>
                        </div>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
