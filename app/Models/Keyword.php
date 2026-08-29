<?php

namespace App\Models;

use App\Services\QueryNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Keyword extends Model
{
    use HasFactory;

    protected $fillable = ['keyword', 'keyword_normalized'];

    protected static function booted(): void
    {
        // Normalisation is never left to the caller. Whether a keyword arrives
        // from the Filament form, a CSV import or a seeder, the stored form is
        // always what QueryNormalizer produces - the same class the incoming
        // search query goes through.
        static::saving(function (Keyword $keyword) {
            $keyword->keyword_normalized = app(QueryNormalizer::class)
                ->normalize($keyword->keyword_normalized ?: $keyword->keyword);
        });
    }

    public function links(): BelongsToMany
    {
        return $this->belongsToMany(Link::class)->withPivot('weight');
    }

    /**
     * Find or create by normalised form, so "Berita", "berita " and "BERITA!"
     * all resolve to one row instead of three near-duplicates.
     */
    public static function findOrCreateByName(string $keyword): self
    {
        $normalized = app(QueryNormalizer::class)->normalize($keyword);

        return static::firstOrCreate(
            ['keyword_normalized' => $normalized],
            ['keyword' => trim($keyword)],
        );
    }
}
