<?php

namespace App\Jobs;

use App\Enums\TrafficSource;
use App\Models\SearchLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecordSearch implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $queryRaw,
        private readonly string $queryNormalized,
        private readonly int $resultCount,
        private readonly TrafficSource $source,
        private readonly ?int $resolvedLinkId = null,
        private readonly ?string $ipHash = null,
        private readonly ?string $userAgent = null,
    ) {}

    public function handle(): void
    {
        SearchLog::create([
            'query_raw' => mb_substr($this->queryRaw, 0, 255),
            'query_normalized' => $this->queryNormalized,
            'result_count' => $this->resultCount,
            'source' => $this->source,
            'resolved_link_id' => $this->resolvedLinkId,
            'ip_hash' => $this->ipHash,
            'user_agent' => mb_substr((string) $this->userAgent, 0, 255) ?: null,
        ]);
    }
}
