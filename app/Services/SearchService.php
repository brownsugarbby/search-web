<?php

namespace App\Services;

use App\Models\Link;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Ranking lives here and nowhere else.
 *
 * Every public entry point - the search page, the "Cari cepat" button, the
 * typeahead - resolves through this class, so they cannot disagree about what
 * "the first match" for a query is.
 *
 * The search runs in tiers and stops at the first tier that produces hits.
 * The ordering is deliberate: an admin who deliberately attached the keyword
 * "berita" to an entry should always beat an entry that merely happens to
 * mention the word in its description.
 */
class SearchService
{
    public function __construct(private readonly QueryNormalizer $normalizer) {}

    /**
     * Ranked matches for a query.
     *
     * @return Collection<int, Link>
     */
    public function search(?string $query): Collection
    {
        $normalized = $this->normalizer->normalize($query);

        if ($normalized === '') {
            return new Collection;
        }

        $key = $this->cacheKey($normalized);
        $ids = Cache::get($key);

        if ($ids === null) {
            $ids = $this->resolveIds($normalized);

            /*
             * A miss is deliberately not cached. An admin who searches for a
             * keyword, finds nothing, adds it, and searches again would
             * otherwise keep seeing nothing until the TTL ran out - which
             * reads as a broken search rather than a cached one. A miss is
             * also the cheap case: one indexed lookup, nothing to protect.
             */
            if ($ids !== []) {
                Cache::put($key, $ids, config('search.cache_ttl'));
            }
        }

        if ($ids === []) {
            return new Collection;
        }

        // Re-fetch through the public scope rather than caching models, so a
        // link deactivated after the query was cached disappears immediately
        // instead of lingering for the rest of the TTL.
        $links = Link::query()
            ->public()
            ->with('category')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        // Restore the ranked order the tiers produced; whereIn() does not
        // preserve it.
        return new Collection(
            array_values(array_filter(array_map(fn ($id) => $links->get($id), $ids)))
        );
    }

    /** Bumped whenever the catalog changes; see forget(). */
    private const VERSION_KEY = 'search:version';

    /**
     * Results resolved under one tier configuration must not be served after
     * that configuration changes, so the key carries it - and so does a
     * version that moves whenever a link or keyword is edited.
     */
    private function cacheKey(string $normalized): string
    {
        $tiers = (int) config('search.tiers.prefix_enabled')
            .(int) config('search.tiers.fulltext_enabled');

        return 'search:'.$this->version().':'.$tiers.':'.sha1($normalized);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Retire every cached result.
     *
     * A version bump rather than a scan: the store is the database driver,
     * which has no tags, and the keys are hashes of queries nobody has a list
     * of. Orphaned entries fall out on their own TTL.
     *
     * This matters most in the other direction from a stale miss: a keyword
     * an admin has just detached must stop redirecting people immediately,
     * not in ten minutes.
     */
    public static function forget(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 1) + 1);
    }

    /** The single link a redirect should send someone to, or null. */
    public function firstMatch(?string $query): ?Link
    {
        return $this->search($query)->first();
    }

    /**
     * Run the tiers and return ranked link ids.
     *
     * Only ids are cached - they are small, and the models are re-read live.
     *
     * @return array<int, int>
     */
    private function resolveIds(string $normalized): array
    {
        $tiers = ['exactIds'];

        // Off by default. A query that is not an exact keyword returns nothing
        // rather than the closest thing we could find - see config/search.php
        // for why guessing is the wrong trade on a site that auto-redirects.
        if (config('search.tiers.prefix_enabled')) {
            $tiers[] = 'prefixIds';
        }

        if (config('search.tiers.fulltext_enabled')) {
            $tiers[] = 'fulltextIds';
        }

        foreach ($tiers as $tier) {
            $ids = $this->{$tier}($normalized);

            if ($ids !== []) {
                return $ids;
            }
        }

        return [];
    }

    /**
     * Tier A - the query is exactly a keyword an admin attached.
     *
     * Indexed equality on keyword_normalized, so this is the cheap path and
     * the one that handles the overwhelming majority of real traffic.
     *
     * @return array<int, int>
     */
    private function exactIds(string $normalized): array
    {
        return $this->rankedPivotQuery()
            ->where('keywords.keyword_normalized', $normalized)
            ->pluck('links.id')
            ->all();
    }

    /**
     * Tier B - the query is the start of a keyword ("toko" -> "tokopedia").
     *
     * A leftmost LIKE still uses the index on keyword_normalized. A leading
     * wildcard would not, which is why infix matching is left to FULLTEXT.
     *
     * @return array<int, int>
     */
    private function prefixIds(string $normalized): array
    {
        return $this->rankedPivotQuery()
            ->where('keywords.keyword_normalized', 'like', $this->escapeLike($normalized).'%')
            ->limit(config('search.tiers.prefix_limit'))
            ->pluck('links.id')
            ->all();
    }

    /**
     * Tier C - relevance against the materialised blob (title + description +
     * every attached keyword).
     *
     * @return array<int, int>
     */
    private function fulltextIds(string $normalized): array
    {
        $terms = $this->normalizer->terms($normalized);

        if ($terms === []) {
            return [];
        }

        // Boolean mode with a trailing * on each term, so a partial word still
        // matches. Terms are normalised (letters, digits, spaces, hyphens only)
        // before they get here, so no boolean operator can survive into the
        // expression.
        $expression = collect($terms)
            ->map(fn (string $term) => str_replace('-', ' ', $term).'*')
            ->implode(' ');

        return Link::query()
            ->public()
            ->whereFullText('search_blob', $expression, ['mode' => 'boolean'])
            ->orderByRaw('MATCH(search_blob) AGAINST (? IN BOOLEAN MODE) DESC', [$expression])
            ->orderByDesc('weight')
            ->orderByDesc('click_count')
            ->limit(config('search.tiers.fulltext_limit'))
            ->pluck('id')
            ->all();
    }

    /**
     * Shared shape for the two keyword tiers.
     *
     * Ordering: the per-pairing weight first (an admin saying "for THIS
     * keyword, show THIS entry first"), then the link's global weight, then
     * real-world popularity as the tie-breaker.
     */
    private function rankedPivotQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Link::query()
            ->public()
            ->join('keyword_link', 'keyword_link.link_id', '=', 'links.id')
            ->join('keywords', 'keywords.id', '=', 'keyword_link.keyword_id')
            ->select('links.id')
            // A link can be reached through several keywords at once (a prefix
            // query matches "bank" and "bank digital"). Group so it appears
            // once, and rank it by its strongest pairing rather than an
            // arbitrary one. GROUP BY also keeps this legal under MySQL's
            // ONLY_FULL_GROUP_BY, which DISTINCT + ORDER BY on a joined column
            // is not.
            ->selectRaw('MAX(keyword_link.weight) as pivot_weight')
            ->groupBy('links.id', 'links.weight', 'links.click_count')
            ->orderByDesc('pivot_weight')
            ->orderByDesc('links.weight')
            ->orderByDesc('links.click_count');
    }

    /** Stop a user's % or _ being treated as a wildcard. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
