<div>
    <h1 class="mb-4 text-xl font-semibold">Quản trị</h1>
    <x-admin-tabs />

    <p class="mb-3 text-xs text-gray-500">Mọi lần xem dữ liệu và mọi thao tác trên tài khoản người dùng đều được ghi lại ở đây.</p>

    <div class="space-y-2">
        @forelse ($logs as $log)
            <div wire:key="log-{{ $log->id }}" class="rounded-xl bg-white p-3 text-sm shadow-sm">
                @include('livewire.admin.partials.log-line', ['log' => $log, 'showUser' => true])
            </div>
        @empty
            <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm">Chưa có nhật ký.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
</div>
