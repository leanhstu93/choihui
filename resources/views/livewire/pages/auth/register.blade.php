<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        // Tài khoản mới là hụi viên (mặc định của cột role); làm chủ hụi thì chuyển trong Cài đặt (04-chu-hui.md C9).
        // Sự kiện sign_up của Google Analytics (đánh dấu là chuyển đổi).
        $this->js('window.gtag?.("event", "sign_up", { method: "email" })');

        $this->redirect(route('net-worth', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="text-center">
        <h1 class="text-xl font-semibold">Mở {{ config('app.name') }} miễn phí</h1>
        <p class="mt-1 text-sm text-gray-600">Một tài khoản cho cả ngày giỗ, sức khỏe, đồ sửa, vàng, hụi.</p>
        <div class="mt-3 flex flex-wrap justify-center gap-1.5 text-xs text-gray-700" aria-hidden="true">
            @foreach (['🕯️ Ngày nhớ', '🩺 Sức khỏe', '🔧 Sửa chữa', '🪙 Vàng', '🤝 Hụi'] as $book)
                <span class="rounded-full bg-gray-100 px-2.5 py-1">{{ $book }}</span>
            @endforeach
        </div>
    </div>

    <form wire:submit="register" class="mt-5 space-y-4">
        <div>
            <x-input-label for="name" value="Tên của bạn" />
            <x-text-input wire:model="name" id="name" class="mt-1 block w-full" type="text" name="name" placeholder="Ví dụ: Lan" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="mt-1 block w-full" type="email" name="email" required autocomplete="username" />
            <p class="mt-1 text-xs text-gray-500">Dùng để đăng nhập và nhận email nhắc ngày giỗ, tái khám.</p>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div x-data="{ show: false }">
            <x-input-label for="password" :value="__('Password')" />
            <div class="relative mt-1">
                <x-text-input wire:model="password" id="password" class="block w-full pe-16" x-bind:type="show ? 'text' : 'password'" type="password" name="password" required autocomplete="new-password" />
                <button type="button" @click="show = ! show" class="absolute inset-y-0 end-0 px-3 text-sm font-medium text-emerald-700" x-text="show ? 'Ẩn' : 'Hiện'">Hiện</button>
            </div>
            <p class="mt-1 text-xs text-gray-500">Ít nhất 8 ký tự.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Nhập lại mật khẩu" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <button type="submit" class="w-full rounded-xl bg-emerald-700 py-3 font-semibold text-white shadow-sm hover:bg-emerald-800" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="register">Tạo tài khoản</span>
            <span wire:loading wire:target="register">Đang tạo…</span>
        </button>

        <p class="text-center text-xs text-gray-500">
            Khi tạo tài khoản, bạn đồng ý với <a href="{{ route('privacy') }}" class="underline hover:text-gray-700">Chính sách bảo mật</a>.
        </p>
    </form>

    <p class="mt-5 border-t border-gray-100 pt-4 text-center text-sm text-gray-600">
        Đã có tài khoản? <a href="{{ route('login') }}" wire:navigate class="font-semibold text-emerald-700 hover:underline">Đăng nhập</a>
    </p>
</div>
