<?php

namespace App\Http\Controllers;

use App\Enums\TrafficSource;
use App\Jobs\RecordSearch;
use App\Services\QueryNormalizer;
use App\Services\SearchService;
use App\Support\Visitor;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;

class SearchController extends Controller
{
    public function __construct(
        private readonly SearchService $search,
        private readonly QueryNormalizer $normalizer,
    ) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $raw = (string) $request->query('q', '');
        $normalized = $this->normalizer->normalize($raw);

        if ($normalized === '') {
            return view('home');
        }

        // "Cari cepat" always jumps, even once the results page is switched on.
        $lucky = $request->boolean('lucky');
        $source = $lucky ? TrafficSource::Lucky : TrafficSource::Direct;

        $results = $this->search->search($normalized);

        RecordSearch::dispatch(
            $raw,
            $normalized,
            $results->count(),
            $source,
            $results->first()?->id,
            Visitor::ipHash($request),
            $request->userAgent(),
        );

        if ($results->isEmpty()) {
            return view('home', [
                'query' => $raw,
                'noResults' => true,
            ]);
        }

        // Phase 1: no list, just go. The results view below is fully built and
        // covered by tests - config('search.results_page_enabled') is the only
        // thing standing between it and production.
        if ($lucky || ! config('search.results_page_enabled')) {
            return redirect()->route('go', $results->first());
        }

        return view('results', [
            'query' => $raw,
            'results' => $results,
        ]);
    }
}
