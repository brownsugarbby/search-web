<?php

namespace App\Http\Controllers;

use App\Enums\TrafficSource;
use App\Jobs\RecordSearch;
use App\Services\LinkResolver;
use App\Support\Visitor;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

/**
 * Handles both /go/{slug} (clicked on-site) and /s/{slug} (opened from a
 * link someone shared). Same resolution, different traffic source.
 */
class RedirectController extends Controller
{
    public function __construct(private readonly LinkResolver $resolver) {}

    public function go(Request $request, string $slug): RedirectResponse
    {
        return $this->handle($request, $slug, TrafficSource::Direct);
    }

    public function share(Request $request, string $slug): RedirectResponse
    {
        return $this->handle($request, $slug, TrafficSource::Share);
    }

    private function handle(Request $request, string $slug, TrafficSource $source): RedirectResponse
    {
        $link = $this->resolver->resolve($slug);

        if ($link === null) {
            return $this->deadLink($request, $slug, $source);
        }

        $url = $this->resolver->record($link, $source, null, Visitor::ipHash($request));

        return redirect()->away($url)->withHeaders([
            // Do not leak this site as the referrer to the destination.
            'Referrer-Policy' => 'no-referrer',
            // Redirect endpoints have no business in a search index.
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    /**
     * The entry is gone, deactivated, or was never real.
     *
     * Land on the homepage rather than a 404, and record it. A dead shared
     * link is the single most actionable thing in the zero-result report:
     * someone is actively handing that URL to other people right now.
     */
    private function deadLink(Request $request, string $slug, TrafficSource $source): RedirectResponse
    {
        RecordSearch::dispatch(
            $slug,
            $slug,
            0,
            $source,
            null,
            Visitor::ipHash($request),
            $request->userAgent(),
        );

        return redirect()->route('home')->with('deadLink', true);
    }
}
