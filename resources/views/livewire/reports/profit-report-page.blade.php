@php
    $profitClass = fn (int $value) => $value >= 0 ? 'text-emerald-700' : 'text-red-700';
@endphp

<div class="space-y-4">
    <h1 class="text-xl font-semibold">Báo cáo lời / lỗ</h1>

    <section class="grid grid-cols-2 gap-2">
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Tổng đã đóng</div><div class="font-semibold">@money($totals['paid'])</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Tổng đã nhận</div><div class="font-semibold">@money($totals['received'])</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Lời / lỗ thực tế</div><div class="font-semibold {{ $profitClass($totals['profit']) }}">@money($totals['profit'])</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Dự kiến khi mãn dây</div><div class="font-semibold {{ $profitClass($totals['projected']) }}">@money($totals['projected'])</div></div>
    </section>

    <p class="text-xs text-gray-500">
        Thực tế = tiền đã nhận − tiền đã đóng. Dự kiến: kỳ chưa mở lấy lãi thăm trung bình các kỳ đã mở, tay chưa hốt giả định hốt ở các kỳ cuối còn trống. Dây bể và dây đã kết thúc chỉ tính thực tế.
    </p>

    <section class="space-y-3">
        @forelse ($groups as $row)
            <a href="{{ route('groups.show', $row['group']) }}" wire:navigate wire:key="report-{{ $row['group']->id }}" class="block rounded-xl bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                    <div class="font-semibold">{{ $row['group']->name }}</div>
                    <span class="shrink-0 text-xs text-gray-500">{{ $row['group']->status->label() }}</span>
                </div>
                <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                    <dt class="text-gray-500">Đã đóng</dt><dd class="text-right">@money($row['paid'])</dd>
                    <dt class="text-gray-500">Đã nhận</dt><dd class="text-right">@money($row['received'])</dd>
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
