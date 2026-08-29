<?php

namespace App\Services;

use App\Enums\TrafficSource;
use App\Jobs\RecordClick;
use App\Models\Link;

/**
 * Turns a slug into a destination.
 *
 * /s/{slug} (a shared link) and /go/{slug} (an on-site click) both come
 * through here. They are the same operation and stay two routes only so the
 * traffic source can be told apart in the reports - which is exactly the
 * number that shows how much traffic is spreading by word of mouth.
 *
 * Resolution happens against the live catalog on every open. That is what
 * makes an already-shared link follow along when an admin edits the entry's
 * destination URL: there is nothing stored in the shared URL except the slug.
 */
class LinkResolver
{
    /**
     * The publicly reachable link for this slug, or null if there isn't one.
     *
     * Null covers deleted, soft-deleted, deactivated and (when the setting is
     * on) unreviewed entries. Callers turn that into the homepage empty state
     * rather than a 404 - someone is holding that link and handing it to other
     * people, so it should be recorded, not silently dead-ended.
     */
    public function resolve(?string $slug): ?Link
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        return Link::query()->public()->where('slug', $slug)->first();
    }

    /** Record the hit off the hot path and hand back the destination. */
    public function record(Link $link, TrafficSource $source, ?string $query = null, ?string $ipHash = null): string
    {
        RecordClick::dispatch($link->id, $source, $query, $ipHash);

        return $link->url;
    }
}
