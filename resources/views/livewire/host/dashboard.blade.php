<div class="space-y-4">
    <x-install-app />

    <section class="rounded-xl bg-emerald-700 p-5 text-white shadow-sm">
        <p class="text-sm text-emerald-100">Còn phải thu</p>
        <p class="mt-1 text-3xl font-semibold">@money($totals['toCollect'])</p>
        <div class="mt-2 space-y-0.5 text-sm text-emerald-100">
            @if ($totals['toPayOut'] > 0)
                <p>Chưa chi cho người hốt: @money($totals['toPayOut'])</p>
            @endif
            @if ($totals['overdueCount'] > 0)
                <p class="font-semibold text-amber-200">{{ $totals['overdueCount'] }} kỳ đã quá hạn đóng</p>
            @endif
        </div>
    </section>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif

    @foreach ($rounds as $row)
        <section class="rounded-xl bg-white shadow-sm" wire:key="round-{{ $row['round']->id }}">
            <a href="{{ route('host.rounds.show', $row['round']) }}" wire:navigate class="flex items-start justify-between gap-2 p-3">
                <div class="min-w-0 text-sm">
                    <div class="truncate font-semibold">{{ $row['group']->name }} · Kỳ {{ $row['round']->round_no }}</div>
                    <div @class(['text-red-700 font-medium' => $row['isOverdue'], 'text-gray-500' => ! $row['isOverdue']])>
                        {{ $row['isOverdue'] ? 'Quá hạn · ' : '' }}Hạn {{ $row['dueDate']->format('d/m/Y') }}
                    </div>
                </div>
                <div class="shrink-0 text-right text-sm">
                    <div class="font-semibold">@money($row['collected'])</div>
                    <div class="text-xs text-gray-500">/ @money($row['expected'])</div>
                </div>
            </a>
            <ul class="divide-y divide-gray-100 border-t border-gray-100">
                @if ($row['payout'])
                    <li class="flex items-center justify-between gap-3 p-3" wire:key="entry-{{ $row['payout']->id }}">
                        <div class="min-w-0 text-sm">
                            <div class="truncate font-medium">Chi · Tay {{ $row['payout']->hostSlot->slot_no }} · {{ $row['payout']->hostSlot->member->name }}</div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-sm font-semibold text-sky-700">@money($row['payout']->amount_expected)</span>
                            <button type="button" wire:click="settleInFull('{{ $row['payout']->id }}')" wire:loading.attr="disabled" class="rounded-lg bg-sky-700 px-3 py-1.5 text-xs font-semibold text-white">Đã chi</button>
                        </div>
                    </li>
                @endif
                @foreach ($row['unpaid'] as $entry)
                    <li class="flex items-center justify-between gap-3 p-3" wire:key="entry-{{ $entry->id }}">
                        <div class="min-w-0 text-sm">
                            <div class="truncate font-medium">Tay {{ $entry->hostSlot->slot_no }} · {{ $entry->hostSlot->member->name }}</div>
                            @if ($entry->amount_actual > 0)
                                <div class="text-gray-500">Đã đóng @money($entry->amount_actual)</div>
                            @endif
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="text-sm font-semibold">@money($entry->amount_expected - $entry->amount_actual)</span>
                            @if ($entry->amount_actual === 0)
                                <button type="button" wire:click="settleInFull('{{ $entry->id }}')" wire:loading.attr="disabled" class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white">Đã thu</button>
                            @else
                                <a href="{{ route('host.rounds.show', $row['round']) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs">Sửa</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    @if ($pendingResults !== [])
        <section>
            <h2 class="mb-2 text-sm font-semibold text-gray-600">Cần nhập kết quả ({{ \App\Livewire\Host\Dashboard::DAYS_AHEAD }} ngày tới)</h2>
            <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                @foreach ($pendingResults as $row)
                    <li wire:key="pending-{{ $row['round']->id }}">
                        <a href="{{ route('host.rounds.show', $row['round']) }}" wire:navigate class="flex items-center justify-between gap-3 p-3 text-sm">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $row['group']->name }} · Kỳ {{ $row['round']->round_no }}</div>
                                <div class="text-gray-500">Mở @date($row['round']->open_date)</div>
                            </div>
                            <span class="shrink-0 rounded-lg border border-gray-300 px-3 py-1.5 text-xs">Nhập kết quả</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($rounds === [] && $pendingResults === [])
        <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm">
            Không có khoản nào cần thu hay chi.
            <a href="{{ route('host.groups.index') }}" wire:navigate class="font-semibold text-emerald-700">Xem các dây hụi</a>
        </div>
    @endif
</div>
