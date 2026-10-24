@php
    $profitClass = fn (int $value) => $value >= 0 ? 'text-emerald-700' : 'text-red-700';
    $buttonClass = 'rounded-lg border px-3 py-2 text-sm font-semibold';
@endphp

<div class="space-y-4">
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.users.index') }}" wire:navigate class="text-emerald-700" aria-label="Quay lại">&larr;</a>
        <h1 class="truncate text-xl font-semibold">{{ $user->name }}</h1>
    </div>

    @if ($actionError)
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $actionError }}</div>
    @endif
    @if ($actionStatus)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ $actionStatus }}</div>
    @endif

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            <div class="col-span-2"><dt class="text-gray-500">Email</dt><dd class="break-all font-medium">{{ $user->email }}</dd></div>
            <div><dt class="text-gray-500">Vai trò</dt><dd class="font-medium">{{ $user->role->label() }}{{ $user->isAdmin() ? ' · Quản trị viên' : '' }}</dd></div>
            <div><dt class="text-gray-500">Trạng thái</dt><dd @class(['font-medium', 'text-red-700' => $user->isLocked()])>{{ $user->isLocked() ? 'Bị khóa từ '.$user->locked_at->format('d/m/Y H:i') : 'Đang hoạt động' }}</dd></div>
            <div><dt class="text-gray-500">Đăng ký</dt><dd class="font-medium">{{ $user->created_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt class="text-gray-500">Dùng lần cuối</dt><dd class="font-medium">{{ $user->last_seen_at?->format('d/m/Y H:i') ?? 'Chưa rõ' }}</dd></div>
            <div><dt class="text-gray-500">Nhắc hạn trước</dt><dd class="font-medium">{{ $user->reminder_days_before > 0 ? $user->reminder_days_before.' ngày' : 'Tắt' }}</dd></div>
            <div><dt class="text-gray-500">Mã PIN</dt><dd class="font-medium">{{ $user->pin_hash ? 'Có' : 'Không' }}</dd></div>
        </dl>
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Thao tác</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($user->isLocked())
                <button type="button" wire:click="unlock" wire:confirm="Mở khóa tài khoản {{ $user->email }}?" class="{{ $buttonClass }} border-emerald-700 text-emerald-700">Mở khóa</button>
            @elseif (! $user->isAdmin())
                <button type="button" wire:click="lock" wire:confirm="Khóa tài khoản {{ $user->email }}? Người dùng bị đăng xuất ngay và không đăng nhập lại được." class="{{ $buttonClass }} border-red-600 text-red-700">Khóa tài khoản</button>
            @endif
            <button type="button" wire:click="sendPasswordReset" wire:confirm="Gửi email đặt lại mật khẩu tới {{ $user->email }}?" class="{{ $buttonClass }} border-gray-300 text-gray-700">Gửi email đặt lại mật khẩu</button>
        </div>

        @if ($report['groups'] === [])
            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-gray-500">Đổi vai trò (chỉ khi chưa có dây):</span>
                @foreach ($roles as $roleOption)
                    @continue($roleOption === $user->role)
                    <button type="button" wire:click="changeRole('{{ $roleOption->value }}')" wire:confirm="Đổi vai trò của {{ $user->email }} thành {{ $roleOption->label() }}?" class="{{ $buttonClass }} border-gray-300 text-gray-700">Đổi thành {{ $roleOption->label() }}</button>
                @endforeach
            </div>
        @endif
    </section>

    <section class="space-y-3">
        <h2 class="font-semibold">Dây hụi ({{ count($report['groups']) }})</h2>

        @if ($report['groups'] !== [])
            <div class="grid grid-cols-2 gap-2 text-center">
                @if ($isHost)
                    <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã thu / đã chi</div><div class="font-semibold">@money($report['totals']['collected'])</div><div class="text-xs text-gray-400">@money($report['totals']['paidOut'])</div></div>
                    <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Lời / lỗ dự kiến</div><div class="font-semibold {{ $profitClass($report['totals']['projected']) }}">@money($report['totals']['projected'])</div><div class="text-xs text-gray-400">nợ @money($report['totals']['debt'])</div></div>
                @else
                    <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Đã đóng / đã nhận</div><div class="font-semibold">@money($report['totals']['paid'])</div><div class="text-xs text-gray-400">@money($report['totals']['received'])</div></div>
                    <div class="rounded-xl bg-white p-3 shadow-sm"><div class="text-xs text-gray-500">Lời / lỗ dự kiến</div><div class="font-semibold {{ $profitClass($report['totals']['outlook']) }}">@money($report['totals']['outlook'])</div></div>
                @endif
            </div>
        @endif

        @forelse ($report['groups'] as $row)
            <a href="{{ route('admin.groups.show', $row['group']->id) }}" wire:navigate wire:key="group-{{ $row['group']->id }}" class="block rounded-xl bg-white p-4 shadow-sm active:bg-gray-50">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="font-semibold">{{ $row['group']->name }}</div>
                        <div class="text-sm text-gray-500">@money($row['group']->face_value) · {{ $row['group']->total_slots }} tay · từ @date($row['group']->start_date)</div>
                    </div>
                    <span class="shrink-0 text-xs text-gray-500">{{ $row['group']->status->label() }}</span>
                </div>
                <div class="mt-2 text-sm">
                    @if ($isHost)
                        Lời / lỗ {{ $row['projected'] !== null ? 'dự kiến' : 'thực tế' }}:
                        <span class="font-semibold {{ $profitClass($row['projected'] ?? $row['profit']) }}">@money($row['projected'] ?? $row['profit'])</span>
                    @else
                        {{ $row['outlook']['label'] }}:
                        <span class="font-semibold {{ $profitClass($row['outlook']['profit']) }}">@money($row['outlook']['profit'])</span>
                    @endif
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm">Chưa có dây hụi nào.</div>
        @endforelse
    </section>

    <section class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Nhật ký quản trị</h2>
        <div class="mt-2 divide-y divide-gray-100 text-sm">
            @forelse ($logs as $log)
                <div class="py-2" wire:key="log-{{ $log->id }}">
                    @include('livewire.admin.partials.log-line', ['log' => $log, 'showUser' => false])
                </div>
            @empty
                <p class="py-2 text-gray-500">Chưa có.</p>
            @endforelse
        </div>
    </section>
</div>
