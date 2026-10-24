<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Livewire\Actions\Logout;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chạy trên mọi request web của người đã đăng nhập (document/05-quan-tri.md):
 * - Tài khoản bị khóa (Q6) bị đăng xuất ngay, kể cả phiên "ghi nhớ đăng nhập".
 * - Ghi last_seen_at (Q9), tối đa 15 phút một lần để không ghi DB mỗi request.
 */
class TouchLastSeen
{
    private const INTERVAL_MINUTES = 15;

    public function __construct(private Logout $logout) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->isLocked()) {
            ($this->logout)();

            if ($request->expectsJson()) {
                abort(401);
            }

            return redirect()->route('login')->with('status', User::LOCKED_MESSAGE);
        }

        if ($user->last_seen_at === null || $user->last_seen_at->lt(now()->subMinutes(self::INTERVAL_MINUTES))) {
            $user->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
