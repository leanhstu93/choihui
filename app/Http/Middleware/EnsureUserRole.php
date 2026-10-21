<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mỗi tài khoản chỉ một vai trò (document/04-chu-hui.md C9): route của vai trò
 * kia trả 404, như thể không tồn tại. Dùng: ->middleware('role:chu_hui').
 */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->role === UserRole::from($role), 404);

        return $next($request);
    }
}
