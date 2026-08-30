<?php

namespace App\Http\Controllers;

use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Does this query lead anywhere?
 *
 * The search form has to choose its target before it submits - a match opens
 * the destination in a new tab, a miss stays in the tab the visitor is in -
 * and only the server knows which it is. So the form asks, ahead of time,
 * while the visitor is still typing.
 *
 * Deliberately thin: a boolean, no titles, no URLs. It is called far more
 * often than a search, from anyone who can reach the homepage, and there is
 * no reason for it to hand out the catalog a keystroke at a time.
 *
 * It also does not record anything. Search history is what a visitor chose to
 * search for, and half-typed words on their way there are not that.
 */
class ResolveController extends Controller
{
    public function __construct(private readonly SearchService $search) {}

    public function __invoke(Request $request): JsonResponse
    {
        $hit = $this->search->firstMatch((string) $request->query('q', '')) !== null;

        return response()->json(['hit' => $hit]);
    }
}
