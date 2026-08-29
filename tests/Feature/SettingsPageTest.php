<?php

use App\Filament\Pages\Settings;
use App\Models\Setting;
use App\Models\User;

use function Pest\Livewire\livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('loads the stored settings into the form', function () {
    Setting::put('site_name', 'Carikan');
    Setting::put('banner_text', 'Pesan lama');

    livewire(Settings::class)
        ->assertFormSet(['site_name' => 'Carikan', 'banner_text' => 'Pesan lama']);
});

it('saves changes and busts the settings cache', function () {
    Setting::put('site_name', 'Lama');

    // Warm the cache so a stale read would be visible.
    expect(Setting::get('site_name'))->toBe('Lama');

    livewire(Settings::class)
        ->fillForm(['site_name' => 'Baru', 'meta_description' => 'Deskripsi baru'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::get('site_name'))->toBe('Baru')
        ->and(Setting::get('meta_description'))->toBe('Deskripsi baru');
});

it('requires a site name', function () {
    livewire(Settings::class)
        ->fillForm(['site_name' => ''])
        ->call('save')
        ->assertHasFormErrors(['site_name']);
});

it('shows the saved site name on the public page', function () {
    livewire(Settings::class)
        ->fillForm(['site_name' => 'Nama Publik'])
        ->call('save');

    $this->get('/')->assertOk()->assertSee('Nama Publik');
});

it('hides unreviewed links from search and from share links when switched on', function () {
    $link = \App\Models\Link::factory()->unreviewed()->create();
    $link->keywords()->attach(\App\Models\Keyword::findOrCreateByName('belum ditinjau'));

    // Reachable while the setting is off.
    $this->get('/?q=belum ditinjau')->assertRedirect(route('go', $link));

    // Set directly: the toggle is no longer on the settings page, but the
    // setting it wrote still governs both doors below.
    Setting::put('hide_unreviewed', true);

    // One toggle has to close both doors - search and share resolution.
    $this->get('/?q=belum ditinjau')->assertOk()->assertSee('Tidak ada hasil');
    $this->get("/s/{$link->slug}")->assertRedirect(route('home'));
});
