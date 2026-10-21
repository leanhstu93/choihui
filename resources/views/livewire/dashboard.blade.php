<div class="space-y-4">
    <section class="rounded-xl bg-emerald-700 p-5 text-white shadow-sm">
        <p class="text-sm text-emerald-100">Cần đóng (quá hạn và {{ \App\Livewire\Dashboard::DAYS_AHEAD }} ngày tới)</p>
        <p class="mt-1 text-3xl font-semibold">@money($totalConfirmed + $totalEstimated)</p>
        <div class="mt-2 space-y-0.5 text-sm text-emerald-100">
            @if ($totalEstimated > 0)
                <p>Trong đó @money($totalEstimated) là ước tính (kỳ chưa có lãi thăm)</p>
            @endif
            @if ($overdueCount > 0)
                <p class="font-semibold text-amber-200">{{ $overdueCount }} khoản đã quá hạn</p>
            @endif
        </div>
    </section>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif

    @forelse ($paymentsByDate as $date => $payments)
        @php $isOverdue = $payments->first()->isOverdue($today); @endphp
        <section wire:key="due-{{ $date }}">
            <h2 @class(['mb-2 text-sm font-semibold', 'text-red-700' => $isOverdue, 'text-gray-600' => ! $isOverdue])>
                {{ $isOverdue ? 'Quá hạn · ' : '' }}Hạn {{ \Illuminate\Support\Carbon::parse($date)->format('d/m/Y') }}
                @if ($date === $today->format('Y-m-d')) · hôm nay @endif
            </h2>
            <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                @foreach ($payments as $payment)
                    <li class="flex items-center justify-between gap-3 p-3" wire:key="payment-{{ $payment->roundId }}-{{ $payment->slotLabel }}">
                        <a href="{{ route('rounds.show', $payment->roundId) }}" wire:navigate class="min-w-0 text-sm">
                            <div class="truncate font-medium">{{ $payment->groupName }}</div>
                            <div class="text-gray-500">Kỳ {{ $payment->roundNo }} · {{ $payment->slotLabel }}</div>
                        </a>
                        <div class="flex shrink-0 items-center gap-2">
                            <span @class(['text-sm font-semibold', 'text-gray-500' => $payment->isEstimate])>
                                {{ $payment->isEstimate ? '~' : '' }}@money($payment->amount)
                            </span>
                            @if ($payment->cashEntryId)
                                <button type="button" wire:click="payInFull('{{ $payment->cashEntryId }}')" wire:loading.attr="disabled"
                                        class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-semibold text-white">Đã đóng</button>
                            @else
                                <a href="{{ route('rounds.show', $payment->roundId) }}" wire:navigate class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs">Nhập lãi</a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm">
            Không có khoản nào phải đóng trong {{ \App\Livewire\Dashboard::DAYS_AHEAD }} ngày tới.
            <a href="{{ route('groups.index') }}" wire:navigate class="font-semibold text-emerald-700">Xem các dây hụi</a>
        </div>
    @endforelse
</div>
