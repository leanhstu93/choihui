<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public string $role = 'hui_vien';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
        ], attributes: ['role' => 'vai trò']);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <form wire:submit="register">
        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Vai trò (document/04-chu-hui.md C9) -->
        <fieldset class="mt-4">
            <legend class="text-sm font-medium text-gray-700">Bạn dùng app để</legend>
            <div class="mt-1 grid grid-cols-2 gap-2 text-sm">
                @foreach (\App\Enums\UserRole::cases() as $option)
                    <label @class(['cursor-pointer rounded-lg border px-3 py-2', 'border-emerald-600 bg-emerald-50 font-semibold' => $role === $option->value, 'border-gray-300' => $role !== $option->value])>
                        <input type="radio" wire:model.live="role" value="{{ $option->value }}" class="sr-only">
                        <span class="block">{{ $option->value === 'chu_hui' ? 'Làm chủ hụi' : 'Chơi hụi' }}</span>
                        <span class="block text-xs font-normal text-gray-500">{{ $option->value === 'chu_hui' ? 'Quản lý dây, thu / chi' : 'Theo dõi tay mình chơi' }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-xs text-gray-500">Mỗi tài khoản một vai trò. Đổi được trong Cài đặt khi chưa có dây nào.</p>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </fieldset>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}" wire:navigate>
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</div>
