@php
    $profitClass = fn (int $value) => $value >= 0 ? 'text-emerald-700' : 'text-red-700';
@endphp

<div class="space-y-4">
    <h1 class="text-xl font-semibold">Báo cáo chủ hụi</h1>

    <section class="grid grid-cols-2 gap-2">
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Thảo đã nhận</div><div class="font-semibold">@money($totals['commissionReceived'])</div><div class="text-xs text-gray-400">dự kiến @money($totals['commissionProjected'])</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Lời / lỗ dự kiến</div><div class="font-semibold {{ $profitClass($totals['projected']) }}">@money($totals['projected'])</div><div class="text-xs text-gray-400">thực tế @money($totals['profit'])</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Hụi viên còn nợ</div><div @class(['font-semibold', 'text-red-700' => $totals['debt'] > 0])>@money($totals['debt'])</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Mình đã bù</div><div class="font-semibold">@money($totals['topUp'])</div><div class="text-xs text-gray-400">bùng @money($totals['writtenOff'])</div></div>
    </section>

    <p class="text-xs text-gray-500">
        Thực tế = tiền đã thu − tiền đã chi. Dự kiến cộng thêm nợ còn đòi được và tiền thảo của các kỳ chưa có người hốt; dây bể hoặc đã kết thúc chỉ tính thực tế. Mình bù = phần chi vượt số đã thu của từng kỳ.
    </p>

    <section class="space-y-3">
        @forelse ($groups as $row)
            <a href="{{ route('host.groups.show', $row['group']) }}" wire:navigate wire:key="report-{{ $row['group']->id }}" class="block rounded-xl bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold">{{ $row['group']->name }}</div>
                    <span class="shrink-0 text-xs text-gray-500">{{ $row['group']->status->label() }}</span>
                </div>
                <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                    <dt class="text-gray-500">Đã thu</dt><dd class="text-right">@money($row['collected'])</dd>
                    <dt class="text-gray-500">Đã chi</dt><dd class="text-right">@money($row['paidOut'])</dd>
                    <dt class="text-gray-500">Thảo đã nhận</dt><dd class="text-right">@money($row['commissionReceived'])</dd>
                    @if ($row['topUp'] > 0)
                        <dt class="text-gray-500">Mình bù</dt><dd class="text-right">@money($row['topUp'])</dd>
                    @endif
                    @if ($row['debt'] > 0)
                        <dt class="text-gray-500">Còn nợ</dt><dd class="text-right text-red-700">@money($row['debt'])</dd>
                    @endif
                    @if ($row['writtenOff'] > 0)
                        <dt class="text-gray-500">Bùng</dt><dd class="text-right">@money($row['writtenOff'])</dd>
                    @endif
                    <dt class="text-gray-500">Thực tế</dt><dd class="text-right font-semibold {{ $profitClass($row['profit']) }}">@money($row['profit'])</dd>
                    @if ($row['projected'] !== null)
                        <dt class="text-gray-500">Dự kiến</dt><dd class="text-right font-semibold {{ $profitClass($row['projected']) }}">@money($row['projected'])</dd>
                    @endif
                </dl>
            </a>
        @empty
            <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm">Chưa có dây hụi nào.</div>
        @endforelse
    </section>
</div>
