<?php

namespace App\Providers;

use App\Models\Link;
use App\Observers\LinkObserver;
use App\Models\Setting;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Link::observe(LinkObserver::class);

        $this->shareSiteChrome();
    }

    /**
     * Site name, logo and the advisory banner appear on every page.
     *
     * Composed here rather than passed by each controller, and read from the
     * settings cache, so a full page render costs no settings queries.
     */
    private function shareSiteChrome(): void
    {
        // Every view, not an enumerated list. A child's
        // @section('title', ... $siteName) is evaluated in the child's own
        // scope before the layout composer runs, so naming views by hand means
        // every new view is one forgotten entry away from a 500. Settings are
        // memoized per request, so the wildcard costs nothing.
        View::composer('*', function ($view) {
            $view->with([
                'siteName' => Setting::get('site_name', config('app.name')),
                'logo' => Setting::get('logo_url'),
                'favicon' => Setting::get('favicon_url', '/favicon.ico'),
                'bannerText' => Setting::get('banner_text'),
                'bannerLink' => Setting::get('banner_link'),
                'metaDescription' => Setting::get('meta_description', ''),
            ]);
        });

        // The homepage empty state offers these as a way out, so they are only
        // fetched for the views that can actually show them.
        View::composer(['home', 'results'], function ($view) {
            $view->with([
                // Deliberately uncached: two indexed LIMIT 8 queries are
                // cheap, and caching Eloquent collections means serialising
                // models into the cache store for no real gain.
                // The homepage shows these up front, not only after a failed
                // search. On a curated directory an empty box tells a visitor
                // nothing about what the site actually holds - showing real
                // entries is the difference between "search" and "browse".
                'popularLinks' => \App\Models\Link::query()
                    ->public()
                    ->with('category')
                    ->orderByDesc('click_count')
                    ->orderByDesc('weight')
                    ->limit(8)
                    ->get(),

                'categories' => \App\Models\Category::query()
                    ->where('is_active', true)
                    ->withCount(['links' => fn ($q) => $q->public()])
                    ->having('links_count', '>', 0)
                    ->orderByDesc('links_count')
                    ->limit(8)
                    ->get(),
            ]);
        });
    }
}
