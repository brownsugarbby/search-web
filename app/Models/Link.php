<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Link extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'slug', 'title', 'url', 'description', 'category_id', 'thumbnail_path',
        'weight', 'is_active', 'is_reviewed', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_reviewed' => 'boolean',
            'weight' => 'integer',
            'click_count' => 'integer',
            'share_open_count' => 'integer',
        ];
    }

    /** Slug is the public identity - /s/{slug} and /go/{slug} both bind on it. */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class)->withPivot('weight');
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class);
    }

    /**
     * The only definition of "publicly reachable".
     *
     * Search results, share resolution and click-throughs all go through this
     * scope, so deactivating a link removes it from every path at once - there
     * is no route that keeps serving an entry the admin has taken down.
     */
    public function scopePublic(Builder $query): Builder
    {
        $query->where('is_active', true);

        if (Setting::get('hide_unreviewed', false)) {
            $query->where('is_reviewed', true);
        }

        return $query;
    }

    /** The URL to hand someone. Resolved against the live catalog when opened. */
    public function shareUrl(): string
    {
        return route('share', $this);
    }

    /**
     * Rebuild the materialised FULLTEXT haystack.
     *
     * Keywords are folded in here so a single MATCH() against one column can
     * rank on them, rather than joining the pivot and scoring per row.
     */
    public function buildSearchBlob(): string
    {
        $parts = [
            $this->title,
            $this->description,
            $this->keywords()->pluck('keyword')->implode(' '),
        ];

        return trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts))) ?? '');
    }
}
