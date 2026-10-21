@php
    $isAfterBreak = fn ($round) => $group->status === \App\Enums\HuiGroupStatus::Be && $round->round_no >= $group->broken_round_no;
    $profitClass = fn (int $value) => $value >= 0 ? 'text-emerald-700' : 'text-red-700';
@endphp

<div x-data="imageExport(@js($fileName))" class="space-y-4">
    <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
            <a href="{{ route('groups.show', $group) }}" wire:navigate class="text-emerald-700" aria-label="Quay lại">&larr;</a>
            <h1 class="text-xl font-semibold">Phiếu tổng kết</h1>
        </div>
        <button type="button" x-on:click="save()" x-bind:disabled="busy"
                class="shrink-0 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-60">
            <span x-show="! busy">Lưu / chia sẻ ảnh</span>
            <span x-show="busy" x-cloak>Đang tạo ảnh…</span>
        </button>
    </div>
    <p x-show="error" x-text="error" x-cloak class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"></p>

    <div class="overflow-x-auto rounded-xl shadow-sm">
        {{-- Rộng cố định 640px và dùng font hệ thống (ảnh không nhúng được font web) để ảnh
             giống hệt màn hình trên mọi thiết bị. Không dùng thuộc tính có dấu ":" (wire:key...)
             trong khối này: html-to-image chuyển sang XML và sẽ lỗi. --}}
        <article x-ref="capture" class="w-[40rem] bg-white p-5 text-gray-900 [font-family:ui-sans-serif,system-ui,sans-serif]">
            <header class="flex items-start justify-between gap-4 border-b border-gray-200 pb-3">
                <div>
                    <h2 class="text-lg font-bold">{{ $group->name }}</h2>
                    <p class="whitespace-nowrap text-xs text-gray-500">Phiếu tổng kết · xuất ngày {{ $exportedAt->format('d/m/Y H:i') }}</p>
                </div>
                <div class="flex shrink-0 items-center gap-2 whitespace-nowrap text-sm font-semibold text-emerald-800">
                    <img src="/icons/icon-192.png" alt="" class="h-7 w-7 rounded-md">
                    {{ config('app.name') }}
                </div>
            </header>

            <dl class="mt-3 grid grid-cols-3 gap-x-4 gap-y-2 text-sm">
                <div><dt class="text-xs text-gray-500">Mệnh giá</dt><dd class="font-medium">@money($group->face_value)</dd></div>
                <div><dt class="text-xs text-gray-500">Số tay / kỳ</dt><dd class="font-medium">{{ $group->total_slots }}</dd></div>
                <div><dt class="text-xs text-gray-500">Ngày mở kỳ 1</dt><dd class="font-medium">@date($group->start_date)</dd></div>
                <div><dt class="text-xs text-gray-500">Chủ hụi</dt><dd class="font-medium">{{ $group->host_name }}</dd></div>
                <div><dt class="text-xs text-gray-500">Tiền thảo</dt><dd class="font-medium">@money($group->commission_amount)</dd></div>
                <div><dt class="text-xs text-gray-500">Trạng thái</dt><dd class="font-medium">{{ $group->status->label() }}@if ($group->broken_round_no) (kỳ {{ $group->broken_round_no }})@endif</dd></div>
                <div class="col-span-3"><dt class="text-xs text-gray-500">Tay của mình</dt>
                    <dd class="font-medium">
                        @foreach ($group->slots as $slot)
                            {{ $slot->label }}{{ $slot->wonRound ? ' (hốt kỳ '.$slot->wonRound->round_no.')' : '' }}{{ $loop->last ? '' : ', ' }}
                        @endforeach
                    </dd>
                </div>
            </dl>

            <table class="mt-4 w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-gray-300 text-left text-xs text-gray-500">
                        <th class="py-1.5 pr-2 font-medium">Kỳ</th>
                        <th class="py-1.5 pr-2 font-medium">Ngày mở</th>
                        <th class="py-1.5 pr-2 text-right font-medium">Lãi thăm</th>
                        <th class="py-1.5 pr-2 font-medium">Người hốt</th>
                        <th class="py-1.5 font-medium">Khoản của mình</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group->rounds as $round)
                        <tr @class(['border-b border-gray-100 align-top', 'text-gray-400' => $isAfterBreak($round) || $round->bid_amount === null])>
                            <td class="py-1.5 pr-2 font-medium">{{ $round->round_no }}</td>
                            <td class="py-1.5 pr-2">@date($round->open_date)</td>
                            <td class="py-1.5 pr-2 text-right">{{ $round->bid_amount === null ? '—' : number_format($round->bid_amount, 0, ',', '.') }}</td>
                            <td class="py-1.5 pr-2">
                                @switch($round->winner_type)
                                    @case(\App\Enums\WinnerType::Minh) <span class="font-semibold text-sky-700">{{ $round->winnerSlot?->label }}</span> @break
                                    @case(\App\Enums\WinnerType::NguoiKhac) {{ $round->winner_name ?: 'Người khác' }} @break
                                    @default —
                                @endswitch
                            </td>
                            <td class="py-1.5">
                                @if ($isAfterBreak($round) && $round->cashEntries->isEmpty())
                                    Bể
                                @endif
                                @foreach ($round->cashEntries->sortBy('slot.label') as $entry)
                                    <div>
                                        {{ $entry->slot->label }}: {{ $entry->direction->label() }} @money($entry->amount_expected)
                                        @if ($entry->amount_actual > 0 && $entry->amount_actual !== $entry->amount_expected)
                                            <span class="text-gray-500">(thực @money($entry->amount_actual))</span>
                                        @endif
                                        <span @class([
                                            'ml-1 text-xs font-semibold',
                                            'text-emerald-700' => in_array($entry->status->value, ['du', 'muon'], true),
                                            'text-orange-700' => $entry->status->value === 'chua',
                                            'text-red-700' => in_array($entry->status->value, ['thieu', 'con_no', 'bung'], true),
                                        ])>{{ $entry->status->label() }}</span>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <dl class="mt-4 grid grid-cols-4 gap-2 text-center text-sm">
                <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">Đã đóng</dt><dd class="font-semibold">@money($summary['paid'])</dd></div>
                <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">Đã nhận</dt><dd class="font-semibold">@money($summary['received'])</dd></div>
                <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">{{ $summary['outlook']['label'] }}</dt><dd class="font-semibold {{ $profitClass($summary['outlook']['profit']) }}">@money($summary['outlook']['profit'])</dd></div>
                <div class="rounded-lg bg-gray-50 p-2"><dt class="text-xs text-gray-500">Nếu hốt kỳ cuối</dt><dd class="font-semibold {{ $summary['ifWinLast'] === null ? '' : $profitClass($summary['ifWinLast']) }}">{{ $summary['ifWinLast'] === null ? '—' : number_format($summary['ifWinLast'], 0, ',', '.').' đ' }}</dd></div>
            </dl>
        </article>
    </div>
</div>
