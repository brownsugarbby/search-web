<?php

namespace App\Console\Commands;

use App\Models\Link;
use App\Observers\LinkObserver;
use Illuminate\Console\Command;

/**
 * Rebuild every link's FULLTEXT haystack.
 *
 * The observer keeps search_blob current in normal use; this is the repair
 * path for bulk imports and for after a change to how the blob is composed.
 */
class SearchReindex extends Command
{
    protected $signature = 'search:reindex';

    protected $description = 'Rebuild the materialised search blob for every link';

    public function handle(LinkObserver $observer): int
    {
        $count = 0;

        Link::withTrashed()->with('keywords')->chunkById(500, function ($links) use ($observer, &$count) {
            foreach ($links as $link) {
                $observer->refreshSearchBlob($link);
                $count++;
            }
        });

        $this->components->info("Reindexed {$count} links.");

        return self::SUCCESS;
    }
}
