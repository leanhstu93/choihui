@php
    $home = ['route' => 'dashboard', 'active' => ['dashboard'], 'label' => 'Trang chủ', 'icon' => 'M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z'];
    $groupsIcon = 'M4 6h16M4 12h16M4 18h10';
    $items = auth()->user()?->isHost() ? [
        $home,
        ['route' => 'host.groups.index', 'active' => ['host.groups.*', 'host.rounds.*'], 'label' => 'Dây hụi', 'icon' => $groupsIcon],
        ['route' => 'host.members.index', 'active' => ['host.members.*'], 'label' => 'Hụi viên', 'icon' => 'M16 19v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1M9 10a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm13 9v-1a4 4 0 0 0-3-3.9M16 4.1a3 3 0 0 1 0 5.8'],
    ] : [
        $home,
        ['route' => 'groups.index', 'active' => ['groups.*', 'rounds.*'], 'label' => 'Dây hụi', 'icon' => $groupsIcon],
        ['route' => 'reports', 'active' => ['reports'], 'label' => 'Báo cáo', 'icon' => 'M4 20V10m6 10V4m6 16v-7m4 7H2'],
    ];
    $items[] = ['route' => 'settings', 'active' => ['settings', 'profile'], 'label' => 'Cài đặt', 'icon' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm7.4-3a7.4 7.4 0 0 0-.1-1.3l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-2.2-1.3L14.4 3h-4l-.4 2.4a7.6 7.6 0 0 0-2.2 1.3l-2.4-1-2 3.4 2 1.6a7.4 7.4 0 0 0 0 2.6l-2 1.6 2 3.4 2.4-1a7.6 7.6 0 0 0 2.2 1.3l.4 2.4h4l.4-2.4a7.6 7.6 0 0 0 2.2-1.3l2.4 1 2-3.4-2-1.6c.1-.4.1-.9.1-1.3Z'];
@endphp

<nav class="fixed bottom-0 inset-x-0 z-20 bg-white border-t border-gray-200" style="padding-bottom: env(safe-area-inset-bottom)" aria-label="Điều hướng chính">
    <div class="max-w-2xl mx-auto grid" style="grid-template-columns: repeat({{ count($items) }}, minmax(0, 1fr))">
        @foreach ($items as $item)
            @php $isActive = request()->routeIs(...$item['active']); @endphp
            <a href="{{ route($item['route']) }}" wire:navigate
               @class(['flex flex-col items-center gap-0.5 py-2 text-xs', 'text-emerald-700 font-semibold' => $isActive, 'text-gray-500' => ! $isActive])
               @if ($isActive) aria-current="page" @endif>
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $item['icon'] }}" /></svg>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
