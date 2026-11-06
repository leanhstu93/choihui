<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<header class="bg-emerald-700 text-white sticky top-0 z-20" style="padding-top: env(safe-area-inset-top)">
    <div class="max-w-2xl mx-auto px-4 h-14 flex items-center justify-between gap-3">
        @php
            $area = session(\App\Http\Middleware\RememberAppArea::SESSION_KEY, \App\Http\Middleware\RememberAppArea::HUI);
            $tabs = [
                ['area' => \App\Http\Middleware\RememberAppArea::OVERVIEW, 'route' => 'net-worth', 'label' => 'Tổng'],
                ['area' => \App\Http\Middleware\RememberAppArea::HUI, 'route' => 'dashboard', 'label' => 'Hụi'],
                ['area' => \App\Http\Middleware\RememberAppArea::GOLD, 'route' => 'gold.index', 'label' => 'Vàng'],
            ];
        @endphp
        <nav class="flex shrink-0 rounded-lg bg-emerald-800 p-0.5 text-sm" aria-label="Chọn sổ">
            @foreach ($tabs as $tab)
                <a href="{{ route($tab['route']) }}" wire:navigate @if ($area === $tab['area']) aria-current="page" @endif
                   @class(['rounded-md px-3 py-1.5', 'bg-white text-emerald-800 font-semibold' => $area === $tab['area'], 'text-emerald-50' => $area !== $tab['area']])>{{ $tab['label'] }}</a>
            @endforeach
        </nav>

        <div x-data="{ open: false }" class="relative min-w-0">
            <button type="button" x-on:click="open = ! open" class="flex min-w-0 items-center gap-1 text-sm text-emerald-50">
                <span class="max-w-24 truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>

            <div x-show="open" x-on:click.outside="open = false" x-cloak
                 class="absolute right-0 mt-2 w-44 rounded-lg bg-white text-gray-800 shadow-lg ring-1 ring-black/5 py-1">
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" wire:navigate class="block px-4 py-2 text-sm hover:bg-gray-50">Quản trị</a>
                @endif
                <a href="{{ route('settings') }}" wire:navigate class="block px-4 py-2 text-sm hover:bg-gray-50">Cài đặt</a>
                <button wire:click="logout" class="block w-full text-start px-4 py-2 text-sm hover:bg-gray-50">Đăng xuất</button>
            </div>
        </div>
    </div>
</header>
