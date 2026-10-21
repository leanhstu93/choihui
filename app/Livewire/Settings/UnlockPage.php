<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Http\Middleware\EnsurePinUnlocked;
use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Màn nhập PIN để mở khóa (FR-13). Sai quá số lần cho phép thì đăng xuất.
 */
#[Layout('layouts.guest')]
#[Title('Mở khóa')]
class UnlockPage extends Component
{
    public string $pin = '';

    public function mount(): void
    {
        if ($this->user()->pin_hash === null) {
            $this->redirectRoute('dashboard', navigate: true);
        }
    }

    public function unlock(Logout $logout): void
    {
        $user = $this->user();
        $throttleKey = 'pin-unlock:'.$user->id;

        if ($user->pin_hash !== null && Hash::check($this->pin, $user->pin_hash)) {
            RateLimiter::clear($throttleKey);
            EnsurePinUnlocked::markUnlocked();
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        RateLimiter::hit($throttleKey, 15 * 60);
        $this->pin = '';

        if (RateLimiter::attempts($throttleKey) >= config('hui.pin_max_attempts')) {
            RateLimiter::clear($throttleKey);
            $logout();
            session()->flash('status', 'Nhập sai PIN quá nhiều lần. Vui lòng đăng nhập lại.');
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $this->addError('pin', 'Mã PIN không đúng.');
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirectRoute('login', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.settings.unlock-page');
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
