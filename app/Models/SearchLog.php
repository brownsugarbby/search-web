<?php

namespace App\Models;

use App\Enums\TrafficSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'query_raw', 'query_normalized', 'result_count',
        'resolved_link_id', 'source', 'ip_hash', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'source' => TrafficSource::class,
            'result_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function resolvedLink(): BelongsTo
    {
        return $this->belongsTo(Link::class, 'resolved_link_id');
    }
}
