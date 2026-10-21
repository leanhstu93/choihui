<?php

declare(strict_types=1);

use App\Models\User;

test('guests see the landing page with sign-up links and share metadata', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Ai chưa đóng')
        ->assertSee(route('register'))
        ->assertSee('<meta property="og:image" content="'.asset('images/og-image.png').'">', false)
        ->assertSee('Hốt kỳ nào thì lời?');

    expect(public_path('images/og-image.png'))->toBeFile();
});

test('signed-in users go straight to their book', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('dashboard'));
});

test('robots and sitemap point to the current domain', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('sitemap.xml'));
    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('<loc>'.url('/').'</loc>', false);
});

test('the privacy policy is public, linked and listed in the sitemap', function () {
    $this->get(route('privacy'))->assertOk()->assertSee('Chính sách bảo mật')->assertSee('không dùng cookie');
    $this->get('/')->assertSee(route('privacy'));
    $this->get('/register')->assertSee(route('privacy'));
    $this->get('/sitemap.xml')->assertSee('<loc>'.route('privacy').'</loc>', false);
});

test('the analytics script is only loaded when a Plausible domain is configured', function () {
    config(['services.plausible.domain' => null]);
    $this->get('/')->assertDontSee('plausible.io/js');

    config(['services.plausible.domain' => 'madeby.io.vn']);
    $this->get('/')->assertSee('data-domain="madeby.io.vn" src="https://plausible.io/js/script.js"', false);
});
