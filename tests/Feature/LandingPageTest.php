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
