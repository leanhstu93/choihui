@php
    $tabs = [
        ['route' => 'admin.dashboard', 'active' => ['admin.dashboard'], 'label' => 'Tổng quan'],
        ['route' => 'admin.users.index', 'active' => ['admin.users.*', 'admin.groups.*'], 'label' => 'Tài khoản'],
        ['route' => 'admin.logs', 'active' => ['admin.logs'], 'label' => 'Nhật ký'],
    ];
@endphp

<nav class="mb-4 flex gap-2 overflow-x-auto" aria-label="Quản trị">
    @foreach ($tabs as $tab)
        @php $isActive = request()->routeIs(...$tab['active']); @endphp
        <a href="{{ route($tab['route']) }}" wire:navigate @if ($isActive) aria-current="page" @endif
           @class(['shrink-0 rounded-full px-3 py-1 text-sm border', 'bg-emerald-700 border-emerald-700 text-white' => $isActive, 'bg-white border-gray-300 text-gray-700' => ! $isActive])>
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
