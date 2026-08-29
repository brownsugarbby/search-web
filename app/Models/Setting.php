<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value', 'type'];

    private const CACHE_KEY = 'settings.all';

    /** Per-request memo. Without it a wildcard view composer would hit the
     *  cache store once per rendered view - dozens of times on an admin page. */
    private static ?array $memo = null;

    protected static function booted(): void
    {
        // Settings are read on essentially every request (site name, banner,
        // hide_unreviewed). They are cached as one blob and busted on write,
        // so a page render costs zero settings queries.
        static::saved(fn () => self::flush());
        static::deleted(fn () => self::flush());
    }

    /**
     * Every setting, cached as one blob.
     *
     * Deliberately not named all() - that would shadow Eloquent's real
     * Model::all(), which the framework and packages call internally.
     *
     * @return array<string, mixed>
     */
    public static function cached(): array
    {
        return self::$memo ??= Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->castValue()])
            ->all());
    }

    public static function flush(): void
    {
        self::$memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::cached()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $type = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'array',
            default => 'string',
        };

        static::updateOrCreate(
            ['key' => $key],
            ['value' => $type === 'array' ? json_encode($value) : (string) $value, 'type' => $type],
        );
    }

    private function castValue(): mixed
    {
        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'array' => json_decode((string) $this->value, true) ?? [],
            default => $this->value,
        };
    }
}
