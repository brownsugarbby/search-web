<?php

namespace App\Models;

use App\Enums\TrafficSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkClick extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['link_id', 'query_normalized', 'source', 'ip_hash'];

    protected function casts(): array
    {
        return [
            'source' => TrafficSource::class,
            'created_at' => 'datetime',
        ];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }
}
