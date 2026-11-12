<?php

declare(strict_types=1);

use App\Models\User;
use Carbon\CarbonImmutable;

test('guests see the landing page with sign-up links and share metadata', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Gọn trong một cuốn sổ.')
        ->assertSee(route('register'))
        ->assertSee('<meta property="og:image" content="'.asset('images/og-image.png').'">', false)
        ->assertSee('Ngày giỗ năm nay rơi vào ngày nào?');

    expect(public_path('images/og-image.png'))->toBeFile();
});

test('signed-in users go straight to their book', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('net-worth'));
});

test('robots and sitemap point to the current domain', function () {
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('sitemap.xml'));
    $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('<loc>'.url('/').'</loc>', false);
});

test('the privacy policy is public, linked and listed in the sitemap', function () {
    $this->get(route('privacy'))->assertOk()->assertSee('Chính sách bảo mật')->assertSee('Google Analytics');
    $this->get('/')->assertSee(route('privacy'));
    $this->get('/register')->assertSee(route('privacy'));
    $this->get('/sitemap.xml')->assertSee('<loc>'.route('privacy').'</loc>', false);
});

test('Google Analytics is only loaded when configured, with cookies denied until consent', function () {
    config(['services.google_analytics.measurement_id' => null]);
    $this->get('/')->assertDontSee('googletagmanager.com')->assertDontSee('cookie-consent');

    config(['services.google_analytics.measurement_id' => 'G-TEST123']);
    $this->get('/')
        ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST123', false)
        ->assertSeeInOrder(["gtag('consent', 'default'", "'denied'", "gtag('config'"], false)
        ->assertSee('id="cookie-consent"', false);
});

test('the lunar date lookup on the landing page gives the next solar date of each lunar day', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-12'));

    $lookup = $this->get('/')->assertOk()->viewData('lunarLookup');

    expect($lookup)->toHaveCount(360)
        ->and($lookup['1/1'])->toBe('2027-02-06')
        ->and($lookup['15/1'])->toBe('2027-02-20');
});
