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
    <div class="max-w-2xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="{{ route('dashboard') }}" wire:navigate class="font-semibold text-lg">{{ config('app.name') }}</a>

        <div x-data="{ open: false }" class="relative">
            <button type="button" x-on:click="open = ! open" class="flex items-center gap-1 text-sm text-emerald-50">
                <span x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
            </button>

            <div x-show="open" x-on:click.outside="open = false" x-cloak
                 class="absolute right-0 mt-2 w-44 rounded-lg bg-white text-gray-800 shadow-lg ring-1 ring-black/5 py-1">
                <a href="{{ route('settings') }}" wire:navigate class="block px-4 py-2 text-sm hover:bg-gray-50">Cài đặt</a>
                <button wire:click="logout" class="block w-full text-start px-4 py-2 text-sm hover:bg-gray-50">Đăng xuất</button>
            </div>
        </div>
    </div>
</header>
