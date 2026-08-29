<?php

use App\Enums\TrafficSource;
use App\Models\Keyword;
use App\Models\Link;
use App\Models\SearchLog;

function taggedLink(string $keyword = 'berita', array $attributes = []): Link
{
    $link = Link::factory()->create($attributes);
    $link->keywords()->attach(Keyword::findOrCreateByName($keyword));

    return $link->fresh();
}

it('redirects a share link straight to the destination', function () {
    $link = taggedLink(attributes: ['url' => 'https://tujuan.example/halaman']);

    $this->get("/s/{$link->slug}")
        ->assertRedirect('https://tujuan.example/halaman');
});

it('never renders a list from a share link, even with the results page on', function () {
    config(['search.results_page_enabled' => true]);
    $link = taggedLink(attributes: ['url' => 'https://tujuan.example/']);

    // The whole point of a share link: turning the results page on later must
    // not change what links people are already holding do.
    $this->get("/s/{$link->slug}")
        ->assertRedirect('https://tujuan.example/');
});

it('follows the destination when an admin edits the url afterwards', function () {
    $link = taggedLink(attributes: ['url' => 'https://lama.example/']);
    $shareUrl = "/s/{$link->slug}";

    $this->get($shareUrl)->assertRedirect('https://lama.example/');

    $link->update(['url' => 'https://baru.example/']);

    // Nothing about the shared URL changed - it carries only the slug, so it
    // resolves against the live catalog every time it is opened.
    $this->get($shareUrl)->assertRedirect('https://baru.example/');
});

it('records a share open separately from an on-site click', function () {
    $link = taggedLink();

    $this->get("/s/{$link->slug}");
    $this->get("/go/{$link->slug}");

    $link->refresh();

    expect($link->share_open_count)->toBe(1)
        ->and($link->click_count)->toBe(1)
        ->and($link->clicks()->pluck('source')->map->value->all())
        ->toEqualCanonicalizing(['share', 'direct']);
});

it('sends a dead share link to the homepage, not a 404', function () {
    $link = taggedLink();
    $slug = $link->slug;
    $link->delete();

    $this->get("/s/{$slug}")
        ->assertRedirect(route('home'))
        ->assertSessionHas('deadLink');
});

it('logs a dead share link so it surfaces in the zero-result report', function () {
    $link = taggedLink();
    $slug = $link->slug;
    $link->update(['is_active' => false]);

    $this->get("/s/{$slug}");

    $log = SearchLog::query()->latest('id')->first();

    // Tagged as a share and with zero results: someone is actively handing
    // that URL to other people, which makes it the most urgent kind of gap.
    expect($log->source)->toBe(TrafficSource::Share)
        ->and($log->result_count)->toBe(0)
        ->and($log->query_normalized)->toBe($slug);
});

it('marks redirects noindex and strips the referrer', function () {
    $link = taggedLink();

    $this->get("/s/{$link->slug}")
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

it('cannot be turned into an open redirect', function () {
    // A slug is not a URL. The property that matters is not which status code
    // comes back - the router rejecting the input outright is just as safe as
    // landing on the homepage - but that no input can ever make these routes
    // send someone to a host an admin did not put in the catalog.
    $attempts = [
        'https://jahat.example',
        '//jahat.example',
        'http:\\/\\/jahat.example',
        '....//jahat.example',
    ];

    foreach ($attempts as $attempt) {
        foreach (['/s/', '/go/'] as $prefix) {
            $response = $this->get($prefix.urlencode($attempt));

            // Cast: a 404 has no Location header at all, which is fine.
            expect((string) $response->headers->get('Location'))->not->toContain('jahat.example')
                ->and(in_array($response->status(), [302, 404], true))->toBeTrue();
        }
    }
});

it('builds a share url from the slug', function () {
    $link = taggedLink();

    expect($link->shareUrl())->toBe(url("/s/{$link->slug}"));
});
