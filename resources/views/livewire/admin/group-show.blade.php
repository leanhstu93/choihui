@php
    $profitClass = fn (int $value) => $value >= 0 ? 'text-emerald-700' : 'text-red-700';
    $statusClass = fn (\App\Enums\CashEntryStatus $status) => match ($status) {
        \App\Enums\CashEntryStatus::Du => 'text-emerald-700',
        \App\Enums\CashEntryStatus::Chua => 'text-gray-500',
        default => 'text-orange-700',
    };
@endphp

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.users.show', $owner) }}" wire:navigate class="text-emerald-700" aria-label="Quay lại">&larr;</a>
        <div class="min-w-0">
            <h1 class="truncate text-xl font-semibold">{{ $group->name }}</h1>
            <div class="truncate text-sm text-gray-500">của {{ $owner->name }} · {{ $owner->email }} · {{ $owner->role->label() }}</div>
        </div>
    </div>

    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800">Chế độ chỉ xem. Lần xem này đã được ghi vào nhật ký quản trị.</div>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <div><dt class="text-gray-500">Mệnh giá</dt><dd class="font-medium">@money($group->face_value)</dd></div>
            <div><dt class="text-gray-500">Số tay / kỳ</dt><dd class="font-medium">{{ $group->total_slots }}</dd></div>
            <div><dt class="text-gray-500">Loại</dt><dd class="font-medium">{{ $group->type->label() }}</dd></div>
            <div><dt class="text-gray-500">Ngày mở kỳ 1</dt><dd class="font-medium">@date($group->start_date)</dd></div>
            <div><dt class="text-gray-500">Tiền thảo</dt><dd class="font-medium">@money($group->commission_amount)</dd></div>
            <div><dt class="text-gray-500">Trạng thái</dt><dd class="font-medium">{{ $group->status->label() }}@if ($group->broken_round_no) (kỳ {{ $group->broken_round_no }})@endif</dd></div>
            @unless ($isHost)
                <div class="col-span-2"><dt class="text-gray-500">Chủ hụi</dt><dd class="font-medium">{{ $group->host_name }}@if ($group->host_phone) · {{ $group->host_phone }}@endif</dd></div>
            @endunless
        </dl>
        @if ($group->note)
            <p class="mt-3 text-sm text-gray-600">{{ $group->note }}</p>
        @endif
    </section>

    <section class="grid grid-cols-2 gap-2 text-center">
        @if ($isHost)
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã thu</div><div class="font-semibold">@money($summary['collected'])</div></div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã chi</div><div class="font-semibold">@money($summary['paidOut'])</div></div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Hụi viên còn nợ</div><div @class(['font-semibold', 'text-red-700' => $summary['debt'] > 0])>@money($summary['debt'])</div></div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Lời / lỗ {{ $summary['projected'] !== null ? 'dự kiến' : '' }}</div><div class="font-semibold {{ $profitClass($summary['projected'] ?? $summary['profit']) }}">@money($summary['projected'] ?? $summary['profit'])</div></div>
        @else
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã đóng</div><div class="font-semibold">@money($summary['paid'])</div></div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã nhận</div><div class="font-semibold">@money($summary['received'])</div></div>
            <div class="col-span-2 rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">{{ $summary['outlook']['label'] }}</div><div class="font-semibold {{ $profitClass($summary['outlook']['profit']) }}">@money($summary['outlook']['profit'])</div></div>
        @endif
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">{{ $isHost ? 'Người giữ tay' : 'Tay của người dùng' }}</h2>
        <ul class="mt-2 divide-y divide-gray-100 text-sm">
            @if ($isHost)
                @foreach ($group->hostSlots as $slot)
                    <li class="flex justify-between gap-2 py-1.5" wire:key="slot-{{ $slot->id }}">
                        <span>Tay {{ $slot->slot_no }} · {{ $slot->member->name }}</span>
                        <span class="text-gray-500">{{ $slot->member->phone }}</span>
                    </li>
                @endforeach
            @else
                @foreach ($group->slots as $slot)
                    <li class="py-1.5" wire:key="slot-{{ $slot->id }}">{{ $slot->label }}</li>
                @endforeach
            @endif
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="font-semibold">Các kỳ</h2>
        @foreach ($group->rounds as $round)
            @php
                $entries = $isHost ? $round->hostEntries->sortBy(fn ($entry) => $entry->hostSlot->slot_no) : $round->cashEntries;
                $winner = match (true) {
                    $isHost && $round->winnerHostSlot !== null => 'Tay '.$round->winnerHostSlot->slot_no.' · '.$round->winnerHostSlot->member->name,
                    $round->winner_type === \App\Enums\WinnerType::Minh => 'Mình ('.$round->winnerSlot?->label.')',
                    $round->winner_type === \App\Enums\WinnerType::NguoiKhac => $round->winner_name ?: 'Người khác',
                    default => null,
                };
            @endphp
            <details class="rounded-xl bg-white shadow-sm" wire:key="round-{{ $round->id }}">
                <summary class="flex cursor-pointer items-center justify-between gap-2 p-3 text-sm">
                    <span class="font-semibold">Kỳ {{ $round->round_no }} · @date($round->open_date)</span>
                    <span class="text-right text-gray-500">
                        @if ($winner !== null)
                            {{ $winner }}@if ($round->bid_amount !== null) · lãi @money($round->bid_amount)@endif
                        @else
                            Chưa có người hốt
                        @endif
                    </span>
                </summary>
                <div class="border-t border-gray-100 px-3 pb-3">
                    @if ($entries->isEmpty())
                        <p class="pt-2 text-sm text-gray-500">Chưa có khoản nào.</p>
                    @else
                        <table class="mt-2 w-full text-sm">
                            <thead class="text-left text-xs text-gray-500">
                                <tr><th class="py-1 font-normal">Khoản</th><th class="py-1 text-right font-normal">Phải</th><th class="py-1 text-right font-normal">Thực</th><th class="py-1 text-right font-normal">Trạng thái</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach ($entries as $entry)
                                    <tr wire:key="entry-{{ $entry->id }}">
                                        <td class="py-1">
                                            {{ $entry->direction->label() }} · {{ $isHost ? 'Tay '.$entry->hostSlot->slot_no.' '.$entry->hostSlot->member->name : $entry->slot->label }}
                                            @if ($entry->paid_date)<div class="text-xs text-gray-400">@date($entry->paid_date)@if ($entry->note) · {{ $entry->note }}@endif</div>@endif
                                        </td>
                                        <td class="py-1 text-right">@money($entry->amount_expected)</td>
                                        <td class="py-1 text-right">@money($entry->amount_actual)</td>
                                        <td class="py-1 text-right {{ $statusClass($entry->status) }}">{{ $entry->status->label() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </details>
        @endforeach
    </section>
</div>
