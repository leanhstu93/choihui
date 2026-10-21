@php
    $stateClasses = [
        'chua_mo' => 'bg-white border-gray-200 text-gray-500',
        'cho_nhap' => 'bg-amber-50 border-amber-300 text-amber-800',
        'can_dong' => 'bg-orange-50 border-orange-300 text-orange-800',
        'qua_han' => 'bg-red-50 border-red-400 text-red-800',
        'thieu' => 'bg-red-50 border-red-300 text-red-700',
        'da_dong' => 'bg-emerald-50 border-emerald-300 text-emerald-800',
        'minh_hot' => 'bg-sky-50 border-sky-400 text-sky-800',
        'be' => 'bg-gray-200 border-gray-300 text-gray-500',
    ];
    $inputClass = 'rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600';
@endphp

<div class="space-y-4">
    <div class="flex items-start justify-between gap-2">
        <div class="flex items-center gap-2">
            <a href="{{ route('groups.index') }}" wire:navigate class="text-emerald-700" aria-label="Quay lại">&larr;</a>
            <h1 class="text-xl font-semibold">{{ $group->name }}</h1>
        </div>
        <a href="{{ route('groups.edit', $group) }}" wire:navigate class="shrink-0 text-sm font-semibold text-emerald-700">Sửa</a>
    </div>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif

    <div class="rounded-xl bg-white p-4 shadow-sm">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <div><dt class="text-gray-500">Mệnh giá</dt><dd class="font-medium">@money($group->face_value)</dd></div>
            <div><dt class="text-gray-500">Số tay / kỳ</dt><dd class="font-medium">{{ $group->total_slots }}</dd></div>
            <div><dt class="text-gray-500">Ngày mở kỳ 1</dt><dd class="font-medium">@date($group->start_date)</dd></div>
            <div><dt class="text-gray-500">Tiền thảo</dt><dd class="font-medium">@money($group->commission_amount)</dd></div>
            <div><dt class="text-gray-500">Chủ hụi</dt><dd class="font-medium">{{ $group->host_name }} @if ($group->host_phone) · {{ $group->host_phone }} @endif</dd></div>
            <div><dt class="text-gray-500">Trạng thái</dt><dd class="font-medium">{{ $group->status->label() }}@if ($group->broken_round_no) (kỳ {{ $group->broken_round_no }})@endif</dd></div>
        </dl>
        @if ($group->note)
            <p class="mt-3 text-sm text-gray-600">{{ $group->note }}</p>
        @endif
    </div>

    <div class="grid grid-cols-3 gap-2 text-center">
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã đóng</div><div class="font-semibold">@money($totalPaid)</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã nhận</div><div class="font-semibold">@money($totalReceived)</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">{{ $outlook['label'] }}</div><div @class(['font-semibold', 'text-emerald-700' => $outlook['profit'] >= 0, 'text-red-700' => $outlook['profit'] < 0])>@money($outlook['profit'])</div></div>
    </div>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Lịch các kỳ</h2>
        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
            @foreach ($group->rounds as $round)
                <a href="{{ route('rounds.show', $round) }}" wire:navigate wire:key="round-{{ $round->id }}"
                   class="rounded-lg border p-2 text-xs {{ $stateClasses[$roundStates[$round->id]['key']] }}">
                    <div class="font-semibold">Kỳ {{ $round->round_no }}</div>
                    <div>@date($round->open_date)</div>
                    <div class="mt-1">{{ $roundStates[$round->id]['label'] }}</div>
                </a>
            @endforeach
        </div>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Tay của mình</h2>
        <ul class="mt-2 divide-y divide-gray-100">
            @foreach ($group->slots as $slot)
                <li class="flex items-center justify-between py-2 text-sm" wire:key="slot-{{ $slot->id }}">
                    <span>{{ $slot->label }}</span>
                    @if ($slot->wonRound)
                        <span class="text-sky-700">Đã hốt kỳ {{ $slot->wonRound->round_no }}</span>
                    @else
                        <span class="flex items-center gap-3">
                            <span class="text-gray-500">Chưa hốt</span>
                            <button type="button" wire:click="removeSlot('{{ $slot->id }}')" wire:confirm="Xóa {{ $slot->label }}?" class="text-red-600">Xóa</button>
                        </span>
                    @endif
                </li>
            @endforeach
        </ul>
        @if ($group->slots->count() < $group->total_slots)
            <form wire:submit="addSlot" class="mt-3 flex gap-2">
                <input type="text" wire:model="newSlotLabel" placeholder="Tay {{ $group->slots->count() + 1 }}" aria-label="Tên tay mới" class="{{ $inputClass }} flex-1">
                <button type="submit" class="rounded-lg bg-emerald-700 px-3 text-sm font-semibold text-white">Thêm</button>
            </form>
            @error('label') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        @endif
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm space-y-3 text-sm">
        <h2 class="font-semibold">Thao tác</h2>

        <a href="{{ route('groups.statement', $group) }}" wire:navigate class="block w-full rounded-lg border border-emerald-600 py-2 text-center font-semibold text-emerald-700">Xuất ảnh phiếu tổng kết</a>

        @if ($group->status === \App\Enums\HuiGroupStatus::DangChay)
            <button type="button" wire:click="finish" class="w-full rounded-lg border border-gray-300 py-2">Kết thúc dây (đã đủ kỳ)</button>

            <form wire:submit="markBroken" class="flex gap-2">
                <input type="number" inputmode="numeric" min="1" max="{{ $group->total_slots }}" wire:model="brokenRoundNo" placeholder="Kỳ bể" aria-label="Kỳ bể" class="{{ $inputClass }} w-28">
                <button type="submit" wire:confirm="Đánh dấu dây bể? Các khoản chưa đóng từ kỳ này sẽ bị hủy." class="flex-1 rounded-lg border border-red-300 py-2 text-red-700">Đánh dấu dây bể</button>
            </form>
            @error('broken_round_no') <p class="text-red-600">{{ $message }}</p> @enderror
        @elseif ($group->status === \App\Enums\HuiGroupStatus::Be)
            <button type="button" wire:click="undoBroken" class="w-full rounded-lg border border-gray-300 py-2">Bỏ đánh dấu bể (nhập nhầm)</button>
        @endif

        <button type="button" wire:click="delete" wire:confirm="Xóa dây {{ $group->name }} và toàn bộ dữ liệu? Không thể hoàn tác." class="w-full rounded-lg py-2 text-red-600">Xóa dây hụi</button>
    </section>
</div>
