<?php

namespace App\Jobs;

use App\Enums\TrafficSource;
use App\Models\Link;
use App\Models\LinkClick;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Writes click history away from the request.
 *
 * A redirect should be as close to instant as possible, so the counters and
 * the history row are written by the queue. With QUEUE_CONNECTION=sync (the
 * documented fallback for a client who cannot run a worker) this simply runs
 * inline - correct either way, just slightly slower.
 */
class RecordClick implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $linkId,
        private readonly TrafficSource $source,
        private readonly ?string $queryNormalized = null,
        private readonly ?string $ipHash = null,
    ) {}

    public function handle(): void
    {
        LinkClick::create([
            'link_id' => $this->linkId,
            'query_normalized' => $this->queryNormalized,
            'source' => $this->source,
            'ip_hash' => $this->ipHash,
        ]);

        // Counters are denormalised so admin tables can sort on them without
        // aggregating the history table on every page load.
        $column = $this->source === TrafficSource::Share ? 'share_open_count' : 'click_count';

        Link::withTrashed()->whereKey($this->linkId)->increment($column);
    }
}
