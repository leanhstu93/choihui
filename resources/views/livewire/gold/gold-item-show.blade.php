@php
    $inputClass = 'w-full rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600';
    $profitClass = fn (int $value) => $value >= 0 ? 'text-emerald-700' : 'text-red-700';
@endphp

<div class="space-y-4">
    <a href="{{ route('gold.index') }}" wire:navigate class="text-sm text-emerald-700">‹ Sổ vàng</a>

    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold">{{ $goldItem->displayName() }}</h1>
            <p class="text-sm text-gray-500">{{ $goldItem->gold_type->label() }}{{ $goldItem->shop ? ' · '.$goldItem->shop : '' }}</p>
        </div>
        <a href="{{ route('gold.edit', $goldItem) }}" wire:navigate class="shrink-0 text-sm text-emerald-700">Sửa</a>
    </div>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <dt class="text-gray-500">Ngày mua</dt><dd class="text-right">@date($goldItem->bought_on)</dd>
            <dt class="text-gray-500">Khối lượng</dt><dd class="text-right">@chi($goldItem->weight_ly) chỉ</dd>
            <dt class="text-gray-500">Đã trả</dt><dd class="text-right">@money($goldItem->cost_amount)</dd>
            @if ($position->soldWeight > 0)
                <dt class="text-gray-500">Đã bán</dt><dd class="text-right">@chi($position->soldWeight) chỉ</dd>
                <dt class="text-gray-500">Tiền bán</dt><dd class="text-right">@money($position->soldAmount)</dd>
                <dt class="text-gray-500">Lời / lỗ đã chốt</dt><dd class="text-right font-semibold {{ $profitClass($position->realized) }}">@money($position->realized)</dd>
            @endif
            @if ($position->isHeld())
                <dt class="text-gray-500">Còn lại</dt><dd class="text-right">@chi($position->remainingWeight) chỉ</dd>
                <dt class="text-gray-500">Vốn còn lại</dt><dd class="text-right">@money($position->remainingCost)</dd>
                @if ($position->value === null)
                    <dt class="text-gray-500">Trị giá</dt><dd class="text-right text-amber-700">Chưa có giá</dd>
                @else
                    <dt class="text-gray-500">Trị giá</dt><dd class="text-right">@money($position->value)</dd>
                    <dt class="text-gray-500">Lời / lỗ tạm tính</dt><dd class="text-right font-semibold {{ $profitClass($position->unrealized) }}">@money($position->unrealized)</dd>
                @endif
            @endif
        </dl>
        @if ($goldItem->note)
            <p class="mt-3 text-sm text-gray-600">{{ $goldItem->note }}</p>
        @endif
    </section>

    @if ($goldItem->photos->isNotEmpty())
        <section class="grid grid-cols-3 gap-2">
            @foreach ($goldItem->photos as $photo)
                <a href="{{ route('gold.photos.show', $photo) }}" target="_blank" rel="noopener" wire:key="photo-{{ $photo->id }}">
                    <img src="{{ route('gold.photos.show', $photo) }}" alt="Ảnh món vàng" loading="lazy" class="aspect-square w-full rounded-lg bg-gray-200 object-cover">
                </a>
            @endforeach
        </section>
    @endif

    @if ($showSell)
        <form wire:submit="sell" class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Bán vàng</h2>
            <div class="grid grid-cols-2 gap-3">
                <x-field label="Số chỉ bán" name="weight_ly" hint="Còn {{ \App\Domain\GoldLedger::formatChi($position->remainingWeight) }} chỉ">
                    <input id="weight_ly" type="text" inputmode="decimal" autocomplete="off" wire:model="saleWeightChi" class="{{ $inputClass }}" required>
                </x-field>
                <x-field label="Ngày bán" name="sold_on">
                    <x-date-input id="sold_on" model="soldOn" />
                </x-field>
            </div>
            <x-field label="Tiền nhận được" name="amount">
                <x-money-input id="amount" model="saleAmount" />
            </x-field>
            <x-field label="Ghi chú" name="note">
                <input id="note" type="text" wire:model="saleNote" class="{{ $inputClass }}">
            </x-field>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white">Lưu</button>
                <button type="button" wire:click="cancelSell" class="rounded-lg border border-gray-300 px-4 py-2 text-sm">Hủy</button>
            </div>
        </form>
    @elseif ($position->isHeld())
        <button type="button" wire:click="openSell" class="w-full rounded-lg bg-emerald-700 py-2 text-sm font-semibold text-white">Bán vàng</button>
    @endif

    @if ($goldItem->sales->isNotEmpty())
        <section class="space-y-2">
            <h2 class="font-semibold">Lịch sử bán</h2>
            <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                @foreach ($goldItem->sales as $sale)
                    <li class="flex items-start justify-between gap-3 p-4 text-sm" wire:key="sale-{{ $sale->id }}">
                        <div class="min-w-0">
                            <div class="font-medium">@chi($sale->weight_ly) chỉ · @money($sale->amount)</div>
                            <div class="text-gray-500">@date($sale->sold_on){{ $sale->note ? ' · '.$sale->note : '' }}</div>
                        </div>
                        <button type="button" wire:click="deleteSale('{{ $sale->id }}')" wire:confirm="Xóa giao dịch bán này?" class="shrink-0 text-red-600">Xóa</button>
                    </li>
                @endforeach
            </ul>
        </section>
    @else
        <button type="button" wire:click="deleteItem" wire:confirm="Xóa món vàng này?" class="w-full text-center text-sm text-red-600">Xóa món này</button>
    @endif
</div>
