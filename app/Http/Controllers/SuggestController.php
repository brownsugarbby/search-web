<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Services\QueryNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Typeahead over the curated keyword list.
 *
 * Returns the destination behind each keyword, not just the word. On a curated
 * directory the useful question is "where would this take me?", and answering
 * it in the dropdown means most visits never need a results page at all.
 */
class SuggestController extends Controller
{
    public function __construct(private readonly QueryNormalizer $normalizer) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Refused at the source, not just hidden in the markup - otherwise
        // the catalog stays enumerable by anyone calling the endpoint directly.
        if (! config('search.suggest_enabled')) {
            return response()->json([]);
        }

        $normalized = $this->normalizer->normalize($request->query('q'));

        if (mb_strlen($normalized) < 2) {
            return response()->json([]);
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $normalized);

        $suggestions = Cache::remember(
            'suggest:v2:'.sha1($normalized),
            config('search.cache_ttl'),
            fn () => Keyword::query()
                ->where('keyword_normalized', 'like', $escaped.'%')
                // Only offer keywords that actually lead somewhere. Suggesting a
                // term that then returns nothing is worse than no suggestion.
                ->whereHas('links', fn ($q) => $q->public())
                ->with(['links' => fn ($q) => $q->public()
                    ->with('category')
                    ->orderByDesc('keyword_link.weight')
                    ->orderByDesc('links.weight')
                    ->orderByDesc('links.click_count')
                    ->limit(1)])
                ->orderByRaw('CHAR_LENGTH(keyword_normalized)')
                ->limit(config('search.suggest_limit'))
                ->get()
                ->map(function (Keyword $keyword) {
                    $link = $keyword->links->first();

                    return $link === null ? null : [
                        'keyword' => $keyword->keyword,
                        'title' => $link->title,
                        'category' => $link->category?->name,
                        'host' => parse_url($link->url, PHP_URL_HOST) ?: null,
                        'slug' => $link->slug,
                    ];
                })
                ->filter()
                ->values()
                ->all(),
        );

        return response()->json($suggestions);
    }
}
