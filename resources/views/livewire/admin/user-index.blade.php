@php $inputClass = 'rounded-lg border-gray-300 focus:border-emerald-600 focus:ring-emerald-600'; @endphp

<div>
    <h1 class="mb-4 text-xl font-semibold">Quản trị</h1>
    <x-admin-tabs />

    <div class="mb-4 space-y-2">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Tìm theo tên hoặc email" aria-label="Tìm tài khoản" class="w-full {{ $inputClass }}">
        <div class="flex flex-wrap items-center gap-2">
            <select wire:model.live="role" aria-label="Lọc vai trò" class="{{ $inputClass }} text-sm">
                <option value="">Mọi vai trò</option>
                @foreach ($roles as $roleOption)
                    <option value="{{ $roleOption->value }}">{{ $roleOption->label() }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model.live="lockedOnly" class="rounded border-gray-300 text-emerald-700 focus:ring-emerald-600">
                Chỉ tài khoản bị khóa
            </label>
        </div>
    </div>

    <div class="space-y-2">
        @forelse ($users as $user)
            <a href="{{ route('admin.users.show', $user) }}" wire:navigate wire:key="user-{{ $user->id }}" class="block rounded-xl bg-white p-4 shadow-sm active:bg-gray-50">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <div class="truncate font-semibold">{{ $user->name }}</div>
                        <div class="truncate text-sm text-gray-500">{{ $user->email }}</div>
                    </div>
                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-700">{{ $user->role->label() }}</span>
                        @if ($user->isLocked())
                            <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700">Bị khóa</span>
                        @endif
                    </div>
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                    <span>{{ $user->hui_groups_count }} dây</span>
                    <span>Đăng ký @date($user->created_at)</span>
                    <span>Dùng lần cuối {{ $user->last_seen_at ? $user->last_seen_at->diffForHumans() : 'chưa rõ' }}</span>
                </div>
            </a>
        @empty
            <div class="rounded-xl bg-white p-6 text-center text-sm text-gray-500 shadow-sm">Không có tài khoản nào khớp.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
</div>
