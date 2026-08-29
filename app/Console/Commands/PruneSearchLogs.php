<?php

namespace App\Console\Commands;

use App\Models\LinkClick;
use App\Models\SearchLog;
use Illuminate\Console\Command;

class PruneSearchLogs extends Command
{
    protected $signature = 'search-logs:prune {--days= : Override the configured retention window}';

    protected $description = 'Delete search and click history past the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('search.log_retention_days'));
        $cutoff = now()->subDays($days);

        $searches = SearchLog::query()->where('created_at', '<', $cutoff)->delete();
        $clicks = LinkClick::query()->where('created_at', '<', $cutoff)->delete();

        $this->components->info("Pruned {$searches} searches and {$clicks} clicks older than {$days} days.");

        return self::SUCCESS;
    }
}
