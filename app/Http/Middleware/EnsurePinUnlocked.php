<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Khóa màn hình bằng PIN (FR-13): người dùng có PIN mà không thao tác quá
 * hui.pin_idle_minutes phút thì phải nhập lại PIN. Chỉ chặn người cầm máy; bảo
 * mật thật vẫn là đăng nhập.
 */
class EnsurePinUnlocked
{
    public const SESSION_UNLOCKED_AT = 'pin.last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->pin_hash === null) {
            return $next($request);
        }

        $lastActivity = $request->session()->get(self::SESSION_UNLOCKED_AT);
        $idleSeconds = config('hui.pin_idle_minutes') * 60;

        if (! is_int($lastActivity) || now()->getTimestamp() - $lastActivity > $idleSeconds) {
            $request->session()->forget(self::SESSION_UNLOCKED_AT);

            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('unlock');
        }

        $request->session()->put(self::SESSION_UNLOCKED_AT, now()->getTimestamp());

        return $next($request);
    }

    /**
     * Đánh dấu phiên đã mở khóa (sau khi nhập đúng PIN hoặc đăng nhập bằng mật khẩu).
     */
    public static function markUnlocked(): void
    {
        session()->put(self::SESSION_UNLOCKED_AT, now()->getTimestamp());
    }
}
