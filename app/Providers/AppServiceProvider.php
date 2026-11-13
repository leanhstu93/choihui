<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\EnsurePinUnlocked;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
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
        // Khóa PIN và quyền quản trị áp dụng cả cho các thao tác Livewire trên trang đang mở.
        Livewire::addPersistentMiddleware([EnsurePinUnlocked::class, EnsureUserIsAdmin::class]);

        // Gửi file trực tiếp: giới hạn theo IP (băm, không lưu IP gốc).
        $ipKey = fn (Request $request): string => hash('sha256', $request->ip().config('app.key'));
        RateLimiter::for('transfer-create', fn (Request $request) => Limit::perHour(200)->by($ipKey($request))
            ->response(fn () => response()->json(['reason' => 'too_many_rooms', 'message' => 'Bạn đã tạo quá nhiều phòng trong 1 giờ. Thử lại sau ít phút.'], 429)));
        RateLimiter::for('transfer-signal', fn (Request $request) => Limit::perMinute(600)->by($ipKey($request)));
        RateLimiter::for('transfer-turn', fn (Request $request) => Limit::perMinute(10)->by($ipKey($request))
            ->response(fn () => response()->json(['reason' => 'too_many_turn_requests', 'message' => 'Bạn hỏi máy chủ trung chuyển quá nhiều lần. Đợi 1 phút rồi thử lại.'], 429)));

        Number::useLocale('vi');
        Carbon::setLocale('vi');

        Blade::directive('money', function ($expression) {
            return "<?php echo number_format($expression, 0, ',', '.') . ' đ'; ?>";
        });

        Blade::directive('chi', function ($expression) {
            return "<?php echo \\App\\Domain\\GoldLedger::formatChi($expression); ?>";
        });

        Blade::directive('weekday', function ($expression) {
            return "<?php echo ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'][\\Illuminate\\Support\\Carbon::parse($expression)->dayOfWeek]; ?>";
        });

        Blade::directive('date', function ($expression) {
            return "<?php echo ($expression) ? \Illuminate\Support\Carbon::parse($expression)->format('d/m/Y') : ''; ?>";
        });
    }
}
