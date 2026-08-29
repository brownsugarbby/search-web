<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Results page
    |---------------------------------------------------------------------------
    |
    | When false (phase 1), a search redirects straight to the first match and
    | the ranked result list is never rendered. The list is fully built and
    | tested either way - flip this to true and run `php artisan config:cache`
    | to switch it on. See docs/DEPLOY.md.
    |
    | Share links (/s/{slug}) ignore this flag entirely: they always redirect.
    | Enabling the list must not change what already-shared links do for the
    | people holding them.
    |
    */

    'results_page_enabled' => env('SEARCH_RESULTS_PAGE', false),

    /*
    |---------------------------------------------------------------------------
    | Result ranking tiers
    |---------------------------------------------------------------------------
    |
    | The search runs in tiers and stops as soon as one produces hits. Limits
    | cap how many rows each tier may contribute before de-duplication.
    |
    */

    /*
    |---------------------------------------------------------------------------
    | Matching tiers
    |---------------------------------------------------------------------------
    |
    | The site matches ONLY exact keywords an admin entered. A query that is
    | not in that list returns nothing - it does not fall back to a similar
    | name, and it never sends the visitor to a site they did not ask for.
    |
    | Guessing is worse than admitting a miss here: the visitor is being
    | redirected away automatically, so a near-miss means silently delivering
    | someone to the wrong destination with no chance to notice.
    |
    | The looser tiers are built and tested, and switched off. Turn one on only
    | if the catalog grows to where near-misses are more helpful than harmful.
    |
    */

    'tiers' => [
        'prefix_enabled' => env('SEARCH_TIER_PREFIX', false),
        'fulltext_enabled' => env('SEARCH_TIER_FULLTEXT', false),
        'prefix_limit' => 50,
        'fulltext_limit' => 50,
    ],

    /*
    |---------------------------------------------------------------------------
    | Homepage
    |---------------------------------------------------------------------------
    |
    | suggest_enabled     - the typeahead dropdown under the search box
    | catalog_preview     - the category chips and "Sering dibuka" grid
    | recent_searches     - the visitor's own last few searches, kept in their
    |                       browser only. Switching this off also stops the
    |                       writing, not just the display: storing something in
    |                       someone's browser that is never shown to them is
    |                       nothing but a stray trace left behind.
    |
    */

    'suggest_enabled' => env('SEARCH_SUGGEST', false),
    'catalog_preview_enabled' => env('SEARCH_CATALOG_PREVIEW', false),
    'recent_searches_enabled' => env('SEARCH_RECENT', false),

    'per_page' => 10,

    /*
    |---------------------------------------------------------------------------
    | Query cache
    |---------------------------------------------------------------------------
    |
    | Resolved queries are cached briefly. The default `database` store means
    | the client needs no Redis; set CACHE_STORE=redis if they have one.
    |
    */

    'cache_ttl' => (int) env('SEARCH_CACHE_TTL', 600),

    /*
    |---------------------------------------------------------------------------
    | Rate limiting (requests per minute, per IP)
    |---------------------------------------------------------------------------
    |
    | This is what stops the curated catalog being trivially scraped.
    |
    */

    'throttle' => [
        'search' => env('SEARCH_THROTTLE_SEARCH', 30),
        'redirect' => env('SEARCH_THROTTLE_REDIRECT', 60),
        'suggest' => env('SEARCH_THROTTLE_SUGGEST', 60),
    ],

    'suggest_limit' => 8,

    /*
    |---------------------------------------------------------------------------
    | Destination URL guard
    |---------------------------------------------------------------------------
    |
    | Hosts an admin may not point a link at. Checked on save. Leave the
    | allowlist empty to permit any host that is not blocked; fill it to run
    | the catalog in strict allowlist mode.
    |
    */

    'blocked_hosts' => array_filter(explode(',', (string) env('SEARCH_BLOCKED_HOSTS', ''))),
    'allowed_hosts' => array_filter(explode(',', (string) env('SEARCH_ALLOWED_HOSTS', ''))),

    /*
    |---------------------------------------------------------------------------
    | CSV import
    |---------------------------------------------------------------------------
    |
    | The panel uploader runs inline inside the HTTP request, so it is capped.
    | Seeding a full catalog belongs in `php artisan links:import`, which has no
    | timeout to fight and no queue worker to depend on.
    |
    */

    'import_max_rows' => (int) env('SEARCH_IMPORT_MAX_ROWS', 2000),

    /*
    |---------------------------------------------------------------------------
    | Log retention (days)
    |---------------------------------------------------------------------------
    */

    'log_retention_days' => (int) env('SEARCH_LOG_RETENTION_DAYS', 90),

    /*
    |---------------------------------------------------------------------------
    | Admin panel path
    |---------------------------------------------------------------------------
    |
    | Deliberately not the default "admin".
    |
    */

    'panel_path' => env('ADMIN_PANEL_PATH', 'panel'),

];
