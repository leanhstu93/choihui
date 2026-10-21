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
        ->assertSee('Cài Sổ hụi lên màn hình chính');
})->with([
    'player' => [fn () => User::factory()->create(), 'dashboard'],
    'host' => [fn () => User::factory()->host()->create(), 'host.dashboard'],
]);

test('the app is installable: manifest and a service worker without caching', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true);
    $serviceWorker = (string) file_get_contents(public_path('sw.js'));

    expect($manifest)->toMatchArray(['display' => 'standalone', 'start_url' => '/dashboard'])
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and($serviceWorker)->toContain("addEventListener('fetch'")
        ->and($serviceWorker)->not->toContain('caches.');
});
