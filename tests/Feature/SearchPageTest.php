<?php

use App\Enums\TrafficSource;
use App\Models\Keyword;
use App\Models\Link;
use App\Models\SearchLog;

function keywordLink(string $keyword, array $attributes = []): Link
{
    $link = Link::factory()->create($attributes);
    $link->keywords()->attach(Keyword::findOrCreateByName($keyword));

    return $link->fresh();
}

it('shows the search box with no query', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Ketik nama situs yang dicari')
        ->assertSee('Telusuri')
        ->assertSee('Cari cepat');
});

it('shows recent searches when switched on', function () {
    config(['search.recent_searches_enabled' => true]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Terakhir dicari')
        ->assertSee("suggest('', true)", escape: false);
});

it('shows what the catalog contains when the preview is switched on', function () {
    config(['search.catalog_preview_enabled' => true]);

    $category = \App\Models\Category::create(['name' => 'Belanja', 'slug' => 'belanja']);
    $link = Link::factory()->create(['title' => 'Tokopedia', 'category_id' => $category->id, 'click_count' => 5]);
    $link->keywords()->attach(Keyword::findOrCreateByName('tokopedia'));

    // A blank box tells a visitor nothing about a curated directory. The
    // homepage has to answer "what is even in here?" without a search.
    $this->get('/')
        ->assertOk()
        ->assertSee('Tokopedia')
        ->assertSee('Belanja')
        ->assertSee(route('category', 'belanja'));
});

it('lets a visitor browse a category without knowing a keyword', function () {
    $category = \App\Models\Category::create(['name' => 'Belanja', 'slug' => 'belanja']);
    $shown = Link::factory()->create(['title' => 'Tokopedia', 'category_id' => $category->id]);
    $hidden = Link::factory()->inactive()->create(['title' => 'Sudah Mati', 'category_id' => $category->id]);

    $this->get('/k/belanja')
        ->assertOk()
        ->assertSee('Tokopedia')
        ->assertDontSee('Sudah Mati');
});

it('404s an unknown or inactive category', function () {
    \App\Models\Category::create(['name' => 'Mati', 'slug' => 'mati', 'is_active' => false]);

    $this->get('/k/tidak-ada')->assertNotFound();
    $this->get('/k/mati')->assertNotFound();
});

it('uses GET so a search is shareable and indexable', function () {
    // The reference site POSTs, which leaves the results at a bare "/" - not
    // shareable, not cacheable, not indexable. The query has to travel in the
    // URL for any of that to work.
    $this->get('/')
        ->assertOk()
        ->assertSee('method="GET"', escape: false)
        ->assertDontSee('name="_token"', escape: false);
});

describe('with the results page off (phase 1)', function () {
    beforeEach(fn () => config(['search.results_page_enabled' => false]));

    it('redirects straight to the first match', function () {
        $link = keywordLink('berita', ['url' => 'https://berita.example/']);

        $this->get('/?q=berita')->assertRedirect(route('go', $link));
    });

    it('shows the empty state when nothing matches', function () {
        $this->get('/?q=tidakadaapapun')
            ->assertOk()
            ->assertSee('Tidak ada hasil, coba dengan kata kunci lain.');
    });
});

describe('with the results page on (phase 2)', function () {
    beforeEach(fn () => config(['search.results_page_enabled' => true]));

    it('renders the ranked list', function () {
        $link = keywordLink('berita', ['title' => 'Portal Berita Utama']);

        $this->get('/?q=berita')
            ->assertOk()
            ->assertSee('Portal Berita Utama')
            ->assertSee('1 hasil untuk');
    });

    it('puts a share button on each result', function () {
        $link = keywordLink('berita');

        $html = $this->get('/?q=berita')->assertOk()->getContent();

        // Blade's @js() JSON-encodes the URL, so slashes arrive escaped.
        // Normalise both that and HTML entities before comparing.
        $normalised = str_replace('\\/', '/', html_entity_decode($html));

        expect($normalised)->toContain($link->shareUrl());
    });

    it('still jumps immediately for Cari cepat', function () {
        $link = keywordLink('berita');

        $this->get('/?q=berita&lucky=1')->assertRedirect(route('go', $link));
    });
});

it('logs every search', function () {
    keywordLink('berita');

    $this->get('/?q=Berita');

    $log = SearchLog::query()->latest('id')->first();

    expect($log->query_raw)->toBe('Berita')
        ->and($log->query_normalized)->toBe('berita')
        ->and($log->result_count)->toBe(1)
        ->and($log->source)->toBe(TrafficSource::Direct);
});

it('logs a zero-result search so the gap is visible in the admin', function () {
    $this->get('/?q=barangyanghilang');

    $log = SearchLog::query()->latest('id')->first();

    expect($log->result_count)->toBe(0)
        ->and($log->resolved_link_id)->toBeNull();
});

it('tags Cari cepat as its own traffic source', function () {
    keywordLink('berita');

    $this->get('/?q=berita&lucky=1');

    expect(SearchLog::query()->latest('id')->first()->source)->toBe(TrafficSource::Lucky);
});

it('stores a hashed visitor token rather than the ip address', function () {
    $this->get('/?q=apa');

    $log = SearchLog::query()->latest('id')->first();

    expect($log->ip_hash)->toHaveLength(64)
        ->and($log->ip_hash)->not->toContain('127.0.0.1');
});

it('rate limits search to stop the catalog being scraped', function () {
    $limit = config('search.throttle.search');

    for ($i = 0; $i < $limit; $i++) {
        expect($this->get('/?q=apa'.$i)->status())->not->toBe(429);
    }

    $this->get('/?q=sekalilagi')->assertStatus(429);
});

it('suggests only keywords that lead somewhere', function () {
    config(['search.suggest_enabled' => true]);

    keywordLink('tokopedia', ['title' => 'Tokopedia']);

    // Attached to a deactivated link, so it must not be offered.
    $hidden = Link::factory()->inactive()->create();
    $hidden->keywords()->attach(Keyword::findOrCreateByName('tokosembunyi'));

    $this->getJson('/suggest?q=toko')
        ->assertOk()
        ->assertJsonFragment(['keyword' => 'tokopedia'])
        ->assertJsonMissing(['keyword' => 'tokosembunyi']);
});

it('tells the visitor where each suggestion leads', function () {
    config(['search.suggest_enabled' => true]);

    $category = \App\Models\Category::create(['name' => 'Belanja', 'slug' => 'belanja']);
    keywordLink('tokopedia', [
        'title' => 'Tokopedia',
        'url' => 'https://www.tokopedia.com',
        'category_id' => $category->id,
    ]);

    // "Where would this take me?" is the useful question on a curated
    // directory, and answering it here means most visits never need a list.
    $this->getJson('/suggest?q=toko')
        ->assertOk()
        ->assertJsonFragment([
            'keyword' => 'tokopedia',
            'title' => 'Tokopedia',
            'host' => 'www.tokopedia.com',
            'category' => 'Belanja',
        ]);
});

it('ignores a one-character query', function () {
    config(['search.suggest_enabled' => true]);

    keywordLink('tokopedia');

    $this->getJson('/suggest?q=t')->assertOk()->assertExactJson([]);
});


describe('a plain search page (the default)', function () {
    it('hides the category chips and the popular grid', function () {
        $category = \App\Models\Category::create(['name' => 'Belanja', 'slug' => 'belanja']);
        keywordLink('tokopedia', ['title' => 'Tokopedia', 'category_id' => $category->id]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Kategori')
            ->assertDontSee('Sering dibuka')
            ->assertDontSee(route('category', 'belanja'));
    });

    it('does not render the typeahead dropdown', function () {
        $this->get('/')->assertOk()->assertDontSee('x-for="(item, i) in items"', escape: false);
    });

    it('does not render recent searches', function () {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Terakhir dicari')
            ->assertDontSee('recentSearches()', escape: false);
    });

    it('tells the page not to record recent searches either', function () {
        // Hiding the list is not enough - the browser must also stop being
        // written to for something the visitor will never be shown.
        $this->get('/')->assertOk()->assertSee("suggest('', false)", escape: false);
    });

    it('refuses to answer the suggest endpoint at all', function () {
        keywordLink('tokopedia');

        // Refused at the source, not merely hidden: otherwise the whole
        // catalog stays enumerable by anyone calling the endpoint directly.
        $this->getJson('/suggest?q=toko')->assertOk()->assertExactJson([]);
    });

    it('tells the visitor it found nothing instead of guessing', function () {
        keywordLink('tokopedia', ['title' => 'Tokopedia']);

        foreach (['toko', 'tokopedi', 'tokopediaa'] as $nearMiss) {
            $this->get('/?q='.$nearMiss)
                ->assertOk()
                ->assertSee('Tidak ada hasil, coba dengan kata kunci lain.')
                ->assertDontSee('Tokopedia');
        }
    });

    it('does not let Cari cepat redirect on a near miss either', function () {
        keywordLink('tokopedia');

        $this->get('/?q=toko&lucky=1')->assertOk()->assertSee('Tidak ada hasil');
    });
});
