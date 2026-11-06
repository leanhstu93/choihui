@php
    $profitClass = fn (int $value) => $value >= 0 ? 'text-emerald-700' : 'text-red-700';
    $hasItems = $held->isNotEmpty() || $soldOut->isNotEmpty();
@endphp

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Sổ vàng</h1>
        <a href="{{ route('gold.create') }}" wire:navigate class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white">+ Thêm món</a>
    </div>

    @if ($hasItems)
        <section class="grid grid-cols-2 gap-2">
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đang giữ</div><div class="font-semibold">@chi($totals['weight']) chỉ</div><div class="text-xs text-gray-400">vốn @money($totals['cost'])</div></div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Trị giá hiện tại</div><div class="font-semibold">@money($totals['value'])</div>@if ($totals['unpriced'] > 0)<div class="text-xs text-amber-700">chưa tính {{ $totals['unpriced'] }} món chưa có giá</div>@endif</div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Lời / lỗ tạm tính</div><div class="font-semibold {{ $profitClass($totals['unrealized']) }}">@money($totals['unrealized'])</div></div>
            <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Lời / lỗ đã chốt</div><div class="font-semibold {{ $profitClass($totals['realized']) }}">@money($totals['realized'])</div></div>
        </section>
    @endif

    @if ($types !== [])
        <section class="rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Giá tiệm mua lại, đồng / chỉ</h2>
            <ul class="mt-2 space-y-3">
                @foreach ($types as $row)
                    <li wire:key="price-{{ $row['type']->value }}">
                        <div class="flex items-baseline justify-between gap-2 text-sm">
                            <label for="price-{{ $row['type']->value }}" class="font-medium">{{ $row['type']->label() }}</label>
                            <span class="text-xs text-gray-500">
                                @if ($row['weight'] > 0) @chi($row['weight']) chỉ · @endif
                                @if ($row['price']) cập nhật @date($row['price']->priced_on) @else chưa có giá @endif
                            </span>
                        </div>
                        <div class="mt-1 flex gap-2">
                            <div class="flex-1"><x-money-input id="price-{{ $row['type']->value }}" model="prices.{{ $row['type']->value }}" /></div>
                            <button type="button" wire:click="savePrice('{{ $row['type']->value }}')" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Lưu</button>
                        </div>
                    </li>
                @endforeach
            </ul>
            @error('price_per_chi')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </section>
    @endif

    <section class="space-y-2">
        @if ($hasItems)
            <h2 class="font-semibold">Món đang giữ</h2>
        @endif
        <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            @forelse ($held as $position)
                <li wire:key="held-{{ $position->item->id }}">
                    <a href="{{ route('gold.show', $position->item) }}" wire:navigate class="flex items-start justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <div class="truncate font-medium">{{ $position->item->displayName() }}</div>
                            <div class="text-sm text-gray-500">
                                @chi($position->remainingWeight) chỉ{{ $position->soldWeight > 0 ? ' còn lại' : '' }} · mua @date($position->item->bought_on)
                            </div>
                        </div>
                        <div class="shrink-0 text-right text-sm">
                            @if ($position->value === null)
                                <div class="text-amber-700">Chưa có giá</div>
                            @else
                                <div class="font-medium">@money($position->value)</div>
                                <div class="{{ $profitClass($position->unrealized) }}">@money($position->unrealized)</div>
                            @endif
                        </div>
                    </a>
                </li>
            @empty
                <li class="p-6 text-center text-sm text-gray-500">{{ $hasItems ? 'Không còn món nào đang giữ.' : 'Chưa có món vàng nào.' }}</li>
            @endforelse
        </ul>
    </section>

    @if ($soldOut->isNotEmpty())
        <section class="space-y-2">
            <h2 class="font-semibold">Đã bán hết</h2>
            <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                @foreach ($soldOut as $position)
                    <li wire:key="sold-{{ $position->item->id }}">
                        <a href="{{ route('gold.show', $position->item) }}" wire:navigate class="flex items-start justify-between gap-3 p-4">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $position->item->displayName() }}</div>
                                <div class="text-sm text-gray-500">@chi($position->item->weight_ly) chỉ · mua @date($position->item->bought_on)</div>
                            </div>
                            <div class="shrink-0 text-sm font-medium {{ $profitClass($position->realized) }}">@money($position->realized)</div>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
