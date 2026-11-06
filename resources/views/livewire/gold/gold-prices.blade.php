<div class="space-y-4">
    <div>
        <h1 class="text-xl font-semibold">Giá vàng</h1>
        <p class="text-sm text-gray-500">Giá tiệm mua lại, đồng / chỉ. Tự nhập để tính trị giá và lời / lỗ.</p>
    </div>

    @error('price_per_chi')
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $message }}</div>
    @enderror

    <ul class="space-y-3 rounded-xl bg-white p-4 shadow-sm">
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
</div>
