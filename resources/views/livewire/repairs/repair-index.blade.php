<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Sổ sửa chữa</h1>
        <a href="{{ route('repairs.items.create') }}" wire:navigate class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">+ Thêm món đồ</a>
    </div>

    @if ($items->isNotEmpty())
        <section class="grid grid-cols-2 gap-2">
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Năm {{ $year }}</div><div class="font-semibold">@money($yearTotal)</div></div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Từ trước đến nay</div><div class="font-semibold">@money($total)</div></div>
        </section>
    @endif

    <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
        @forelse ($items as $item)
            <li wire:key="item-{{ $item->id }}">
                <a href="{{ route('repairs.show', $item) }}" wire:navigate class="flex items-start justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <div class="truncate font-medium">{{ $item->name }}</div>
                        <div class="text-sm text-gray-500">
                            @if ($item->records_count > 0)
                                {{ $item->records_count }} lần · gần nhất @date(\Carbon\CarbonImmutable::parse($item->records_max_repaired_on))
                            @else
                                Chưa có lần sửa nào
                            @endif
                        </div>
                    </div>
                    <div class="shrink-0 text-sm font-medium">@money((int) $item->records_sum_amount)</div>
                </a>
            </li>
        @empty
            <li class="p-6 text-center text-sm text-gray-500">Chưa có món đồ nào. Thêm xe, điện thoại, laptop... để ghi lại các lần sửa.</li>
        @endforelse
    </ul>
</div>
