@php
    $maxDaily = max(1, max(array_column($stats['signups']['daily'], 'count')));
    $statusLabels = collect(\App\Enums\HuiGroupStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()]);
@endphp

<div class="space-y-4">
    <h1 class="text-xl font-semibold">Quản trị</h1>
    <x-admin-tabs />

    <section class="grid grid-cols-2 gap-2">
        <a href="{{ route('admin.users.index') }}" wire:navigate class="rounded-xl bg-white p-3 shadow-sm">
            <div class="text-xs text-gray-500">Tài khoản</div>
            <div class="text-2xl font-semibold">{{ number_format($stats['users']['total'], 0, ',', '.') }}</div>
            <div class="text-xs text-gray-400">{{ $stats['users']['hui_vien'] }} hụi viên · {{ $stats['users']['chu_hui'] }} chủ hụi</div>
        </a>
        <div class="rounded-xl bg-white p-3 shadow-sm">
            <div class="text-xs text-gray-500">Đăng ký mới</div>
            <div class="text-2xl font-semibold">{{ $stats['signups']['today'] }} <span class="text-sm font-normal text-gray-500">hôm nay</span></div>
            <div class="text-xs text-gray-400">{{ $stats['signups']['week'] }} trong 7 ngày · {{ $stats['signups']['month'] }} trong 30 ngày</div>
        </div>
        <div class="rounded-xl bg-white p-3 shadow-sm">
            <div class="text-xs text-gray-500">Đang dùng</div>
            <div class="text-2xl font-semibold">{{ $stats['active']['week'] }} <span class="text-sm font-normal text-gray-500">7 ngày</span></div>
            <div class="text-xs text-gray-400">{{ $stats['active']['month'] }} trong 30 ngày</div>
        </div>
        <a href="{{ route('admin.users.index', ['lockedOnly' => 1]) }}" wire:navigate class="rounded-xl bg-white p-3 shadow-sm">
            <div class="text-xs text-gray-500">Bị khóa</div>
            <div @class(['text-2xl font-semibold', 'text-red-700' => $stats['users']['locked'] > 0])>{{ $stats['users']['locked'] }}</div>
        </a>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Đăng ký {{ \App\Queries\AdminStats::SIGNUP_DAYS }} ngày qua</h2>
        <div class="mt-3 flex h-32 items-end gap-0.5" role="img" aria-label="Biểu đồ số tài khoản đăng ký mỗi ngày trong {{ \App\Queries\AdminStats::SIGNUP_DAYS }} ngày qua">
            @foreach ($stats['signups']['daily'] as $day)
                <div class="group relative flex h-full flex-1 items-end" wire:key="day-{{ $day['date'] }}">
                    <div class="w-full rounded-t bg-emerald-600" style="height: {{ $day['count'] > 0 ? max(4, round($day['count'] / $maxDaily * 100)) : 0 }}%"></div>
                    <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-gray-900 px-2 py-1 text-xs text-white group-hover:block">
                        @date($day['date']): {{ $day['count'] }}
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-xs text-gray-400">
            <span>@date($stats['signups']['daily'][0]['date'])</span>
            <span>Hôm nay</span>
        </div>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Dây hụi</h2>
        <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
            <dt class="text-gray-500">Tổng số dây</dt><dd class="text-right font-semibold">{{ $stats['groups']['total'] }}</dd>
            @foreach ($stats['groups']['byStatus'] as $status => $count)
                <dt class="text-gray-500">{{ $statusLabels[$status] }}</dt><dd class="text-right">{{ $count }}</dd>
            @endforeach
            <dt class="text-gray-500">Của hụi viên</dt><dd class="text-right">{{ $stats['groups']['byRole']['hui_vien'] }}</dd>
            <dt class="text-gray-500">Của chủ hụi</dt><dd class="text-right">{{ $stats['groups']['byRole']['chu_hui'] }}</dd>
        </dl>
    </section>

    <p class="text-xs text-gray-500">"Đang dùng" tính theo lần cuối mở app, ghi từ ngày có trang quản trị (23/10/2026).</p>
</div>
