<div class="space-y-4">
    <a href="{{ route('repairs.index') }}" wire:navigate class="text-sm text-emerald-700">‹ Sổ sửa chữa</a>

    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold">{{ $repairItem->name }}</h1>
            @if ($repairItem->note)
                <p class="text-sm text-gray-500">{{ $repairItem->note }}</p>
            @endif
        </div>
        <a href="{{ route('repairs.edit', $repairItem) }}" wire:navigate class="shrink-0 text-sm text-emerald-700">Sửa</a>
    </div>

    <section class="grid grid-cols-2 gap-2">
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã tốn</div><div class="font-semibold">@money($total)</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Số lần</div><div class="font-semibold">{{ count($entries) }}</div></div>
    </section>

    <a href="{{ route('repairs.records.create', $repairItem) }}" wire:navigate class="block w-full rounded-lg bg-emerald-700 py-2 text-center text-sm font-semibold text-white">+ Thêm lần sửa</a>

    @if (count($parts) > 1)
        <section class="space-y-2">
            <h2 class="font-semibold">Theo hạng mục</h2>
            <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
                @foreach ($parts as $part)
                    <li class="flex items-start justify-between gap-3 p-4 text-sm">
                        <div class="min-w-0">
                            <div class="font-medium">{{ $part->name }}</div>
                            <div class="text-gray-500">{{ $part->count }} lần{{ $part->inUseFor ? ' · lần cuối cách đây '.$part->inUseFor : '' }}</div>
                        </div>
                        <div class="shrink-0 font-medium">@money($part->total)</div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="space-y-2">
        <h2 class="font-semibold">Lịch sử</h2>
        <ul class="divide-y divide-gray-100 rounded-xl bg-white shadow-sm">
            @forelse ($entries as $entry)
                @php $record = $entry->record; @endphp
                <li class="space-y-1 p-4 text-sm" wire:key="record-{{ $record->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 font-medium">{{ $record->part }}</div>
                        <div class="shrink-0 font-medium">@money($record->amount)</div>
                    </div>
                    <div class="text-gray-500">
                        @date($record->repaired_on){{ $record->place ? ' · '.$record->place : '' }}{{ $record->odometer_km !== null ? ' · '.number_format($record->odometer_km, 0, ',', '.').' km' : '' }}
                    </div>
                    @if ($entry->lastedFor || $entry->lastedKm)
                        <div class="text-emerald-700">
                            Lần trước dùng được {{ collect([$entry->lastedFor, $entry->lastedKm ? number_format($entry->lastedKm, 0, ',', '.').' km' : null])->filter()->join(' · ') }}
                        </div>
                    @endif
                    @if ($record->warranty_until)
                        <div @class(['text-emerald-700' => $record->isUnderWarranty($today), 'text-gray-500' => ! $record->isUnderWarranty($today)])>
                            {{ $record->isUnderWarranty($today) ? 'Còn bảo hành đến' : 'Hết bảo hành từ' }} @date($record->warranty_until)
                        </div>
                    @endif
                    @if ($record->note)
                        <p class="text-gray-600">{{ $record->note }}</p>
                    @endif
                    @if ($record->photos->isNotEmpty())
                        <div class="grid grid-cols-4 gap-2 pt-1">
                            @foreach ($record->photos as $photo)
                                <a href="{{ route('repairs.photos.show', $photo) }}" target="_blank" rel="noopener" wire:key="photo-{{ $photo->id }}">
                                    <img src="{{ route('repairs.photos.show', $photo) }}" alt="Ảnh hóa đơn" loading="lazy" class="aspect-square w-full rounded-lg bg-gray-200 object-cover">
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <a href="{{ route('repairs.records.edit', $record) }}" wire:navigate class="inline-block pt-1 text-emerald-700">Sửa</a>
                </li>
            @empty
                <li class="p-6 text-center text-sm text-gray-500">Chưa có lần sửa nào.</li>
            @endforelse
        </ul>
    </section>

    <button type="button" wire:click="deleteItem" wire:confirm="Xóa món đồ này cùng toàn bộ lịch sử sửa và ảnh? Không lấy lại được." class="w-full text-center text-sm text-red-600">Xóa món đồ này</button>
</div>
