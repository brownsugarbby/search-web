<?php

use App\Models\Page;
use App\Models\Setting;

it('serves the peringatan page at its own named url', function () {
    Page::create([
        'slug' => 'peringatan',
        'title' => 'Peringatan',
        'body' => '<p>Perjudian dilarang.</p>',
    ]);

    $this->get('/peringatan')
        ->assertOk()
        ->assertSee('Peringatan')
        ->assertSee('Perjudian dilarang.', escape: false);
});

it('serves the same page through the generic /p/ route', function () {
    Page::create(['slug' => 'peringatan', 'title' => 'Peringatan', 'body' => '<p>Isi.</p>']);

    $this->get('/p/peringatan')->assertOk()->assertSee('Peringatan');
});

it('404s an inactive or unknown page', function () {
    Page::create(['slug' => 'mati', 'title' => 'Mati', 'body' => '', 'is_active' => false]);

    $this->get('/p/mati')->assertNotFound();
    $this->get('/p/tidak-ada')->assertNotFound();
});

it('links the advisory banner to the warning page', function () {
    Setting::put('banner_text', 'Gunakan internet dan layanan kami dengan bijak.');
    Setting::put('banner_link', '/peringatan');

    $this->get('/')
        ->assertOk()
        ->assertSee('Gunakan internet dan layanan kami dengan bijak.')
        ->assertSee('/peringatan');
});

it('hides the banner entirely when no text is set', function () {
    Setting::put('banner_text', '');

    $this->get('/')->assertOk()->assertDontSee('Baca disini.');
});

it('advertises the named page urls in the sitemap, not the generic ones', function () {
    Page::create(['slug' => 'peringatan', 'title' => 'Peringatan', 'body' => '']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee(url('/peringatan'))
        ->assertDontSee(url('/p/peringatan'));
});

it('keeps the redirect endpoints out of robots.txt', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /s/')
        ->assertSee('Disallow: /go/');
});
