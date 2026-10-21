<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsurePinUnlocked;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Khóa PIN áp dụng cả cho các thao tác Livewire trên trang đang mở.
        Livewire::addPersistentMiddleware([EnsurePinUnlocked::class]);

        Number::useLocale('vi');
        Carbon::setLocale('vi');

        Blade::directive('money', function ($expression) {
            return "<?php echo number_format($expression, 0, ',', '.') . ' đ'; ?>";
        });

        Blade::directive('date', function ($expression) {
            return "<?php echo ($expression) ? \Illuminate\Support\Carbon::parse($expression)->format('d/m/Y') : ''; ?>";
        });
    }
}
