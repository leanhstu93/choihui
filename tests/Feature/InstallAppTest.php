<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Cài app lên màn hình chính (PWA): thẻ "Cài app" ở trang chủ của cả hai vai trò,
 * service worker và manifest để trình duyệt cho phép cài.
 */

test('both home pages show the install app card', function (Closure $makeUser, string $routeName) {
    $this->actingAs($makeUser())->get(route($routeName))
        ->assertOk()
        ->assertSee('x-data="installApp"', false)
        ->assertSee('Cài '.config('app.name').' lên màn hình chính');
})->with([
    'player' => [fn () => User::factory()->create(), 'dashboard'],
    'host' => [fn () => User::factory()->host()->create(), 'host.dashboard'],
]);

test('the app is installable: manifest and a service worker that never serves pages from cache', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
    $serviceWorker = (string) file_get_contents(public_path('sw.js'));

    expect($manifest)->toMatchArray(['display' => 'standalone', 'start_url' => '/tong-tai-san'])
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and($serviceWorker)->toContain("addEventListener('fetch'")
        // Không trả trang từ cache (tránh hiện số tiền cũ). Cache chỉ dùng cất tạm file "Chia sẻ" vào /gui-file.
        ->and($serviceWorker)->not->toContain('caches.match')
        ->and($serviceWorker)->not->toContain('cache.match')
        ->and($serviceWorker)->toContain("const SHARE_CACHE = 'gui-file-chia-se'")
        ->and(substr_count($serviceWorker, 'caches.open('))->toBe(1)
        ->and($serviceWorker)->toContain('caches.open(SHARE_CACHE)');
});
