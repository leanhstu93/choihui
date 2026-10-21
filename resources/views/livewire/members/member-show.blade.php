<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('host.members.index') }}" wire:navigate class="text-emerald-700" aria-label="Quay lại">&larr;</a>
        <div>
            <h1 class="text-xl font-semibold">{{ $member->name }}</h1>
            <p class="text-sm text-gray-500">
                @if ($member->phone)
                    <a href="tel:{{ $member->phone }}" class="text-emerald-700">{{ $member->phone }}</a>
                @else
                    Chưa có SĐT
                @endif
                @if ($member->note) · {{ $member->note }} @endif
            </p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-2 text-center">
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã đóng</div><div class="font-semibold">@money($ledger['paid'])</div></div>
        <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Còn nợ</div><div @class(['font-semibold', 'text-red-700' => $ledger['debt'] > 0])>@money($ledger['debt'])</div></div>
    </div>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Các tay đang giữ</h2>
        <ul class="mt-2 divide-y divide-gray-100">
            @forelse ($ledger['slots'] as $row)
                <li class="py-3 text-sm" wire:key="slot-{{ $row['slot']->id }}">
                    <a href="{{ route('host.groups.show', $row['slot']->group) }}" wire:navigate class="block">
                        <div class="flex items-start justify-between gap-2">
                            <div class="font-medium">{{ $row['slot']->group->name }} · Tay {{ $row['slot']->slot_no }}</div>
                            @if ($row['slot']->wonRound)
                                <span class="shrink-0 text-sky-700">Hốt kỳ {{ $row['slot']->wonRound->round_no }}</span>
                            @else
                                <span class="shrink-0 text-gray-500">Chưa hốt</span>
                            @endif
                        </div>
                        <div class="mt-1 text-gray-500">
                            Đã đóng @money($row['paid'])
                            @if ($row['debt'] > 0) · <span class="font-medium text-red-700">Nợ @money($row['debt'])</span> @endif
                        </div>
                    </a>
                </li>
            @empty
                <li class="py-3 text-sm text-gray-500">Chưa giữ tay nào.</li>
            @endforelse
        </ul>
    </section>
</div>
