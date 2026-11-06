<div class="space-y-4">
    <x-install-app />

    <section class="rounded-xl bg-emerald-700 p-5 text-white shadow-sm">
        <p class="text-sm text-emerald-100">Tài sản ròng</p>
        <p class="mt-1 text-3xl font-semibold">@money($net)</p>
        <p class="mt-2 text-sm text-emerald-100">Tài sản @money($assets) · Nợ @money($debts)</p>
    </section>

    @if ($goldUnpriced > 0)
        <a href="{{ route('gold.prices') }}" wire:navigate class="block rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Chưa tính {{ $goldUnpriced }} món vàng chưa có giá. Nhập giá ›
        </a>
    @endif

    <section class="space-y-2">
        <h2 class="font-semibold">Tài sản gồm</h2>
        <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            <li>
                <a href="{{ route('gold.index') }}" wire:navigate class="flex items-center justify-between gap-3 p-4">
                    <span class="font-medium">Vàng</span>
                    <span class="font-medium">@money($gold)</span>
                </a>
            </li>
            @unless ($isHost)
                <li>
                    <a href="{{ route('groups.index') }}" wire:navigate class="flex items-center justify-between gap-3 p-4">
                        <span class="min-w-0">
                            <span class="block font-medium">Hụi chưa hốt</span>
                            <span class="block text-sm text-gray-500">Tiền đã đóng</span>
                        </span>
                        <span class="shrink-0 font-medium">@money($huiPaid)</span>
                    </a>
                </li>
            @endunless
        </ul>
    </section>

    @if ($huiDebt > 0)
        <section class="space-y-2">
            <h2 class="font-semibold">Nợ gồm</h2>
            <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                @foreach ($groups as $row)
                    @if ($row['debt'] > 0)
                        <li wire:key="debt-{{ $row['group']->id }}">
                            <a href="{{ route('groups.show', $row['group']) }}" wire:navigate class="flex items-center justify-between gap-3 p-4">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium">{{ $row['group']->name }}</span>
                                    <span class="block text-sm text-gray-500">Đã hốt, còn phải đóng</span>
                                </span>
                                <span class="shrink-0 font-medium text-red-700">@money($row['debt'])</span>
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
            <p class="text-xs text-gray-500">Tiền hốt về chưa được cộng vào tài sản, vì app chưa biết tiền đó đang ở đâu.</p>
        </section>
    @endif

    @if ($isHost)
        <p class="text-xs text-gray-500">Các dây bạn làm chủ không tính vào đây, vì tiền thu chi là của hụi viên.</p>
    @endif

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Tài sản ròng theo tháng</h2>
        @php
            $values = $snapshots->map(fn ($snapshot) => $snapshot->net());
            $positivePeak = max(0, $values->max() ?? 0);
            $negativePeak = max(0, -($values->min() ?? 0));
            $range = max(1, $positivePeak + $negativePeak);
            $bars = $snapshots->map(fn ($snapshot) => [
                'label' => \Illuminate\Support\Carbon::createFromFormat('!Y-m', $snapshot->month)->format('m/Y'),
                'value' => number_format($snapshot->net(), 0, ',', '.').' đ',
            ])->all();
        @endphp
        <div x-data="{ bars: {{ \Illuminate\Support\Js::from($bars) }}, selected: {{ $snapshots->count() - 1 }} }" class="mt-2">
            <p class="text-sm text-gray-500"><span x-text="bars[selected]?.label"></span>: <span class="font-semibold text-gray-900" x-text="bars[selected]?.value"></span></p>
            <div class="mt-3 flex h-32 gap-0.5">
                @foreach ($snapshots as $index => $snapshot)
                    @php $value = $snapshot->net(); @endphp
                    <button type="button" x-on:click="selected = {{ $index }}" aria-label="{{ $bars[$index]['label'] }}: {{ $bars[$index]['value'] }}" class="flex h-full max-w-10 flex-1 flex-col" wire:key="bar-{{ $snapshot->month }}">
                        <span class="flex w-full items-end justify-center" style="height: {{ $positivePeak / $range * 100 }}%">
                            @if ($value > 0)
                                <span class="w-3 rounded-t" x-bind:class="selected === {{ $index }} ? 'bg-emerald-800' : 'bg-emerald-400'" style="height: {{ max(2, $value / $positivePeak * 100) }}%"></span>
                            @endif
                        </span>
                        <span class="block w-full border-t border-gray-300"></span>
                        <span class="flex w-full items-start justify-center" style="height: {{ $negativePeak / $range * 100 }}%">
                            @if ($value < 0)
                                <span class="w-3 rounded-b" x-bind:class="selected === {{ $index }} ? 'bg-emerald-800' : 'bg-emerald-400'" style="height: {{ max(2, -$value / $negativePeak * 100) }}%"></span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>
            <div class="mt-1 flex gap-0.5 text-center text-[10px] text-gray-500" aria-hidden="true">
                @foreach ($snapshots as $snapshot)
                    <span class="max-w-10 flex-1">{{ substr($snapshot->month, 5) }}</span>
                @endforeach
            </div>
        </div>
        @if ($snapshots->count() === 1)
            <p class="mt-2 text-xs text-gray-500">Mỗi tháng có một cột. Số của tháng cập nhật khi bạn mở trang này.</p>
        @endif
    </section>
</div>
