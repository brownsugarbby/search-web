<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\RedirectController;
use App\Http\Controllers\ResolveController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SuggestController;
use Illuminate\Support\Facades\Route;

$searchThrottle = 'throttle:'.config('search.throttle.search').',1';
$redirectThrottle = 'throttle:'.config('search.throttle.redirect').',1';
$suggestThrottle = 'throttle:'.config('search.throttle.suggest').',1';

/*
| Homepage and search are the same route. With no ?q= it renders the search
| box; with one it either redirects to the first match or - once the results
| page is switched on - renders the ranked list.
|
| GET rather than the reference site's POST, so a search is shareable,
| cacheable and indexable.
*/
Route::get('/', SearchController::class)->middleware($searchThrottle)->name('home');

/*
| Share links. Always redirect straight to the entry, regardless of whether
| the results page is enabled - turning the list on later must not change what
| links people are already holding do.
*/
Route::get('/s/{slug}', [RedirectController::class, 'share'])
    ->middleware($redirectThrottle)
    ->name('share');

/* On-site click-through. Same work as /s/, recorded as a different source. */
Route::get('/go/{slug}', [RedirectController::class, 'go'])
    ->middleware($redirectThrottle)
    ->name('go');

/* Browse by category - a way in for visitors who do not know a keyword yet. */
Route::get('/k/{slug}', CategoryController::class)
    ->middleware($searchThrottle)
    ->name('category');

Route::get('/suggest', SuggestController::class)
    ->middleware($suggestThrottle)
    ->name('suggest');

/*
| Whether a query leads anywhere, asked by the search form before it submits
| so that only a match opens a new tab. Shares the typeahead's throttle: it is
| called on the same rhythm, as someone types.
*/
Route::get('/resolve', ResolveController::class)
    ->middleware($suggestThrottle)
    ->name('resolve');

/* Static CMS pages, plus the two the reference site exposes by name. */
Route::get('/peringatan', fn () => app(PageController::class)('peringatan'))->name('peringatan');
Route::get('/privacy-policy', fn () => app(PageController::class)('privacy-policy'))->name('privacy-policy');
Route::get('/p/{slug}', PageController::class)->name('page');

Route::get('/robots.txt', function () {
    // Redirect endpoints must never be indexed; the destination belongs to
    // whoever owns it, not to this site's search presence.
    $body = "User-agent: *\nDisallow: /s/\nDisallow: /go/\nDisallow: /suggest\nDisallow: /resolve\nDisallow: /"
        .config('search.panel_path')."\n\nSitemap: ".url('/sitemap.xml')."\n";

    return response($body, 200, ['Content-Type' => 'text/plain']);
});

Route::get('/sitemap.xml', function () {
    $urls = collect([url('/')]);

    // Prefer the named URL where one exists, so the sitemap advertises the
    // address visitors and the banner actually link to.
    $named = ['peringatan' => '/peringatan', 'privacy-policy' => '/privacy-policy'];

    \App\Models\Page::query()->where('is_active', true)->pluck('slug')
        ->each(fn ($slug) => $urls->push(url($named[$slug] ?? '/p/'.$slug)));

    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
        .$urls->map(fn ($u) => '<url><loc>'.e($u).'</loc></url>')->implode('')
        .'</urlset>';

    return response($xml, 200, ['Content-Type' => 'application/xml']);
});
