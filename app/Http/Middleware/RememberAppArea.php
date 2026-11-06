<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ghi nhớ khu người dùng đang ở (hui / vang, document/06-vang.md V12), để các trang
 * dùng chung như Cài đặt vẫn giữ đúng thanh điều hướng của khu đó.
 */
class RememberAppArea
{
    public const SESSION_KEY = 'app_area';

    public const GOLD = 'vang';

    public const HUI = 'hui';

    public function handle(Request $request, Closure $next, string $area): Response
    {
        $request->session()->put(self::SESSION_KEY, $area);

        return $next($request);
    }
}
