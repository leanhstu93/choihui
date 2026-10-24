<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trang quản trị (document/05-quan-tri.md QT-01): người không phải admin nhận
 * 404, như thể trang không tồn tại.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->isAdmin(), 404);

        return $next($request);
    }
}
