<?php

use App\Models\Keyword;
use App\Models\Link;
use App\Services\SearchService;

beforeEach(function () {
    $this->search = app(SearchService::class);
});

it('returns nothing for an empty query', function () {
    expect($this->search->search('')->all())->toBe([])
        ->and($this->search->firstMatch(null))->toBeNull();
});

it('tier A: matches an exact keyword', function () {
    $link = Link::factory()->create(['title' => 'Situs Berita']);
    $link->keywords()->attach(Keyword::findOrCreateByName('berita'));
    $link->refresh();

    expect($this->search->firstMatch('berita')?->id)->toBe($link->id);
});

it('tier A: matches regardless of the case and punctuation typed', function () {
    $link = Link::factory()->create();
    $link->keywords()->attach(Keyword::findOrCreateByName('Toko Pedia'));

    expect($this->search->firstMatch('  TOKO   pedia!! ')?->id)->toBe($link->id);
});

it('tier A beats tier C: a deliberate keyword outranks an incidental mention', function () {
    config(['search.tiers.fulltext_enabled' => true]);

    // This entry only mentions the word in its description.
    Link::factory()->create([
        'title' => 'Blog Pribadi',
        'description' => 'Membahas berita setiap hari.',
    ]);

    $tagged = Link::factory()->create(['title' => 'Portal Utama']);
    $tagged->keywords()->attach(Keyword::findOrCreateByName('berita'));

    // The admin's explicit tagging must win, and the incidental match must not
    // even appear - tier A short-circuits the rest.
    $results = $this->search->search('berita');

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($tagged->id);
});

it('tier B: matches a keyword prefix when switched on', function () {
    config(['search.tiers.prefix_enabled' => true]);

    $link = Link::factory()->create();
    $link->keywords()->attach(Keyword::findOrCreateByName('tokopedia'));

    expect($this->search->firstMatch('toko')?->id)->toBe($link->id);
});

it('tier C: falls through to fulltext on title and description when switched on', function () {
    config(['search.tiers.fulltext_enabled' => true]);

    $link = Link::factory()->create([
        'title' => 'Kompas Nasional',
        'description' => 'Liputan olahraga dan politik.',
    ]);

    expect($this->search->firstMatch('olahraga')?->id)->toBe($link->id);
});

it('tier C: ranks on keywords folded into the search blob when switched on', function () {
    config(['search.tiers.fulltext_enabled' => true]);

    $link = Link::factory()->create(['title' => 'Situs A', 'description' => 'Tanpa kata itu.']);
    $link->keywords()->attach(Keyword::findOrCreateByName('perbankan digital'));
    app(\App\Observers\LinkObserver::class)->refreshSearchBlob($link->fresh()->load('keywords'));

    // "digital" is neither the title nor the description - it reaches FULLTEXT
    // only because the observer folded the keyword into search_blob.
    expect($this->search->firstMatch('digital')?->id)->toBe($link->id);
});

it('orders by pivot weight, then link weight, then clicks', function () {
    $keyword = Keyword::findOrCreateByName('bank');

    $low = Link::factory()->create(['weight' => 0, 'title' => 'Bank C']);
    $mid = Link::factory()->create(['weight' => 5, 'title' => 'Bank B']);
    $top = Link::factory()->create(['weight' => 0, 'title' => 'Bank A']);

    $low->keywords()->attach($keyword, ['weight' => 0]);
    $mid->keywords()->attach($keyword, ['weight' => 0]);
    $top->keywords()->attach($keyword, ['weight' => 10]);

    expect($this->search->search('bank')->pluck('id')->all())
        ->toBe([$top->id, $mid->id, $low->id]);
});

it('excludes inactive links from every tier', function () {
    $link = Link::factory()->inactive()->create(['title' => 'Tersembunyi']);
    $link->keywords()->attach(Keyword::findOrCreateByName('rahasia'));

    expect($this->search->firstMatch('rahasia'))->toBeNull()
        ->and($this->search->firstMatch('tersembunyi'))->toBeNull();
});

it('excludes unreviewed links when the setting is on', function () {
    \App\Models\Setting::put('hide_unreviewed', true);

    $link = Link::factory()->unreviewed()->create();
    $link->keywords()->attach(Keyword::findOrCreateByName('belum'));

    expect($this->search->firstMatch('belum'))->toBeNull();
});

it('does not let a wildcard in the query widen the match', function () {
    $link = Link::factory()->create();
    $link->keywords()->attach(Keyword::findOrCreateByName('tokopedia'));

    // A bare % must be treated as a literal, not "match everything".
    expect($this->search->firstMatch('%'))->toBeNull();
});


describe('exact matching only (the default)', function () {
    /*
     * The site matches ONLY keywords an admin entered. Anything else returns
     * nothing at all - no near-miss, no redirect.
     *
     * This matters more than it looks: the site redirects automatically, so a
     * near-miss does not show someone a wrong-looking result they can ignore,
     * it silently delivers them to a site they never asked for.
     */

    it('returns nothing for a partial keyword', function () {
        $link = Link::factory()->create();
        $link->keywords()->attach(Keyword::findOrCreateByName('tokopedia'));

        expect($this->search->firstMatch('toko'))->toBeNull()
            ->and($this->search->firstMatch('tokoped'))->toBeNull();
    });

    it('returns nothing for a word that only appears in the title', function () {
        Link::factory()->create(['title' => 'Kompas Nasional']);

        expect($this->search->firstMatch('kompas'))->toBeNull();
    });

    it('returns nothing for a word that only appears in the description', function () {
        Link::factory()->create([
            'title' => 'Situs',
            'description' => 'Liputan olahraga dan politik.',
        ]);

        expect($this->search->firstMatch('olahraga'))->toBeNull();
    });

    it('returns nothing for a misspelling of a real keyword', function () {
        $link = Link::factory()->create();
        $link->keywords()->attach(Keyword::findOrCreateByName('bukalapak'));

        expect($this->search->firstMatch('bukalapk'))->toBeNull();
    });

    it('still matches the exact keyword', function () {
        $link = Link::factory()->create();
        $link->keywords()->attach(Keyword::findOrCreateByName('bukalapak'));

        expect($this->search->firstMatch('  BukaLapak! ')?->id)->toBe($link->id);
    });

    it('does not serve results cached under a different tier setting', function () {
        $link = Link::factory()->create();
        $link->keywords()->attach(Keyword::findOrCreateByName('tokopedia'));

        config(['search.tiers.prefix_enabled' => true]);
        expect($this->search->firstMatch('toko')?->id)->toBe($link->id);

        // Switching the tier off must take effect immediately, not after the
        // cache entry resolved under the old rules expires.
        config(['search.tiers.prefix_enabled' => false]);
        expect($this->search->firstMatch('toko'))->toBeNull();
    });
});
