# Search App

A curated keyword → URL directory, in the shape of carikankita.id / aksesmudah.id.

A visitor types a name and is taken to the site they wanted. It is **not** a web
crawler: search only matches entries an admin has entered, and an unknown term returns
an empty state.

- **Public site** — minimal Indonesian search page at `/`
- **Admin panel** — Filament at `/panel` (path configurable, deliberately not `/admin`)
- **Share links** — `/s/{slug}` sends the recipient straight to one specific entry

## Stack

Laravel 13 · Filament 5 · MySQL 8 · Tailwind 4 · Pest

PHP 8.3 is the target; Composer is pinned to it so no dependency can be installed that
production cannot run.

## Local setup

```bash
docker compose up -d          # MySQL 8.4 on port 13306
composer install
npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate
php artisan app:install       # admin user + default settings/pages
php artisan db:seed --class=DemoSeeder
php artisan serve
```

## How search resolves

**Exact keywords only.** A query that is not a keyword an admin entered returns
nothing — no near-miss, no "did you mean", no redirect.

That is a deliberate trade. The site redirects automatically, so a near-miss does not
show someone a wrong-looking result they can ignore; it silently delivers them to a
site they never asked for. Admitting a miss is the safer failure.

| Tier | Match | Index used | Default |
|---|---|---|---|
| A | Query **is** an attached keyword | equality on `keyword_normalized` | **on** |
| B | Query is the **start** of a keyword | leftmost `LIKE` on the same index | off |
| C | Relevance on title + description + keywords | `FULLTEXT(links.search_blob)` | off |
| D | Nothing | — | logged as a zero-result |

Tiers B and C are built and tested but switched off (`config/search.php`). Turn one on
only if the catalog grows to where near-misses help more than they mislead. Tier A
always wins where it hits: a keyword an admin deliberately attached beats an entry that
merely mentions the word.

`search_blob` is a materialised column holding title + description + every attached
keyword, rebuilt by `LinkObserver`. It backs tier C, and is kept current whether or not
that tier is on so switching it on needs no reindex. Measured at **27–67 ms cold on
100,000 rows**, ~2 ms warm.

Both keyword tiers share `SearchService::firstMatch()`, so every entry point agrees on
what "the first match" is.

## Behaviour

| Entry point | Default (`SEARCH_RESULTS_PAGE=false`) | With the flag on |
|---|---|---|
| `/?q=berita` (exact keyword) | 302 to first match | Ranked list + share buttons |
| `/?q=berit` (not a keyword) | Empty state, logged | Empty state, logged |
| `/?q=berita&lucky=1` | 302 to first match | 302 to first match |
| `/s/{slug}` | 302 to that entry | 302 to that entry |

## Feature flags

All in `config/search.php`, all off by default, all covered by tests in both states.

| Flag | Env | What it turns on |
|---|---|---|
| `results_page_enabled` | `SEARCH_RESULTS_PAGE` | Ranked result list + per-result share button |
| `tiers.prefix_enabled` | `SEARCH_TIER_PREFIX` | Matching the start of a keyword |
| `tiers.fulltext_enabled` | `SEARCH_TIER_FULLTEXT` | Relevance matching on title/description |
| `suggest_enabled` | `SEARCH_SUGGEST` | Typeahead dropdown (endpoint refuses while off) |
| `catalog_preview_enabled` | `SEARCH_CATALOG_PREVIEW` | Homepage category chips + "Sering dibuka" |
| `recent_searches_enabled` | `SEARCH_RECENT` | "Terakhir dicari" (also gates writing to the browser) |

Flags rather than commented-out code: commented code is invisible to the compiler and
the test suite and rots. Each of these compiles, is exercised by tests in both states,
and turns on with one `.env` line plus `php artisan config:cache`.

The results page is **built and tested, switched off** — see `config/search.php`. Tests
cover both branches.

## Share links

`/s/{slug}` identifies **one entry**, not a query. It carries only the slug and resolves
against the live catalog on every open, which means:

- Editing an entry's destination URL updates **every share already in circulation**.
- Slugs are never reused after deletion; a recycled slug would silently point old shared
  links at an unrelated destination.
- A deleted or deactivated entry sends the visitor to the homepage empty state, **not a
  404**, and is logged as `source=share`. Those rank first in *Pencarian Tanpa Hasil* —
  someone is actively handing that URL to other people.
- `/s/` and `/go/` accept a slug, never a URL, so neither can be turned into an open
  redirect. There is a test asserting exactly that.

While the results page is off, share URLs come from the **"Salin tautan"** action on
each row of the admin links table.

## Tests

```bash
php artisan test
```

Tests run against **real MySQL**, not sqlite — the ranking depends on FULLTEXT indexes
that sqlite has no equivalent for.

They also use `DatabaseTruncation` rather than `RefreshDatabase`: InnoDB does not add
uncommitted rows to a FULLTEXT index, so under a transaction-wrapping trait every row a
test creates is invisible to `MATCH()` and tier C looks broken while working perfectly
in production.

## Deployment

See [docs/DEPLOY.md](docs/DEPLOY.md).
