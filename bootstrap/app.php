<?php

declare(strict_types=1);

use App\Http\Middleware\EnsurePinUnlocked;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\RememberAppArea;
use App\Http\Middleware\TouchLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['pin' => EnsurePinUnlocked::class, 'role' => EnsureUserRole::class, 'admin' => EnsureUserIsAdmin::class, 'area' => RememberAppArea::class]);
        $middleware->web(append: [TouchLastSeen::class]);
        // Chia sẻ file từ app khác (PWA share target) là POST do hệ điều hành gửi, không có token CSRF.
        $middleware->validateCsrfTokens(except: ['gui-file/chia-se']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
