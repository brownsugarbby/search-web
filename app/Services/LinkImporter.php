<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Keyword;
use App\Models\Link;
use App\Observers\LinkObserver;
use App\Rules\AllowedDestination;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Bulk CSV import for the catalog.
 *
 * Shared by the artisan command and the panel action so there is one definition
 * of what a valid row is - an import that succeeds over SSH and fails in the
 * browser (or vice versa) would be worse than having only one of the two.
 *
 * The file is streamed row by row rather than read into an array: seeding a
 * catalog of this size is the whole point, and holding 100k rows in memory to
 * do it would be a needless way to run out.
 */
class LinkImporter
{
    /** Columns we understand. Anything else in the header is ignored. */
    private const COLUMNS = [
        'slug', 'title', 'url', 'description', 'keywords',
        'category', 'weight', 'is_active', 'is_reviewed',
    ];

    public function __construct(private readonly LinkObserver $observer) {}

    /**
     * @return array{created:int, updated:int, skipped:int, errors:array<int, string>}
     */
    public function import(string $path, bool $dryRun = false, ?callable $onRow = null): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => ["Tidak bisa membuka berkas: {$path}"]];
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        try {
            $header = $this->readHeader($handle);

            if ($header === null) {
                $result['errors'][] = 'Header CSV wajib memuat kolom "slug" atau "title".';

                return $result;
            }

            $line = 1;

            while (($row = fgetcsv($handle, escape: '\\')) !== false) {
                $line++;

                // Skip blank lines rather than reporting them as errors; a
                // trailing newline is not a mistake worth telling anyone about.
                if ($row === [null] || $row === []) {
                    continue;
                }

                $data = $this->mapRow($header, $row);
                $error = $this->applyRow($data, array_values($header), $dryRun, $result);

                if ($error !== null) {
                    $result['skipped']++;
                    // Cap the report. A malformed file produces one error per
                    // row, and 100k of them helps nobody.
                    if (count($result['errors']) < 50) {
                        $result['errors'][] = "Baris {$line}: {$error}";
                    }
                }

                if ($onRow !== null) {
                    $onRow($line);
                }
            }
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /** @return array<int, string>|null Column index => canonical name. */
    private function readHeader($handle): ?array
    {
        $raw = fgetcsv($handle, escape: '\\');

        if ($raw === false) {
            return null;
        }

        $header = [];

        foreach ($raw as $index => $name) {
            // Strip a UTF-8 BOM - Excel writes one, and it would otherwise make
            // the first column "\u{FEFF}title" and the whole file unusable.
            $name = strtolower(trim(preg_replace('/^\x{FEFF}/u', '', (string) $name) ?? ''));

            if (in_array($name, self::COLUMNS, true)) {
                $header[$index] = $name;
            }
        }

        $values = array_values($header);

        // The file needs some way to say which entry each row is about. What it
        // may leave out beyond that depends on whether the row turns out to be
        // a create or an update, which is decided per row.
        return (in_array('slug', $values, true) || in_array('title', $values, true)) ? $header : null;
    }

    /**
     * @return array<string, string>
     *
     * Every known column is present in the result, even when the file omits
     * it. A CSV carrying only the columns someone actually wanted to change is
     * a completely reasonable file to hand us, and the alternative is guarding
     * every single field at the point of use and missing one.
     */
    private function mapRow(array $header, array $row): array
    {
        $data = array_fill_keys(self::COLUMNS, '');

        foreach ($header as $index => $name) {
            $data[$name] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $present  Columns the file actually carries.
     */
    private function applyRow(array $data, array $present, bool $dryRun, array &$result): ?string
    {
        $slug = $data['slug'];
        $existing = $slug !== '' ? Link::withTrashed()->where('slug', $slug)->first() : null;

        // A new entry has to arrive complete. An existing one is being amended,
        // so only the fields the file actually carries are checked - requiring
        // a title in order to change a weight would make bulk edits impossible.
        $required = $existing === null ? ['required'] : ['sometimes', 'filled'];

        // Absent columns are hidden from the validator entirely, so 'sometimes'
        // means what it says.
        $subject = array_intersect_key($data, array_flip($present));

        $validator = Validator::make($subject, [
            'title' => [...$required, 'string', 'max:255'],
            'url' => [...$required, 'url', new AllowedDestination],
            'description' => ['nullable', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return implode(' ', $validator->errors()->all());
        }

        if ($dryRun) {
            $existing ? $result['updated']++ : $result['created']++;

            return null;
        }

        $candidates = [
            'title' => fn () => $data['title'],
            'url' => fn () => $data['url'],
            'description' => fn () => $data['description'] ?: null,
            'weight' => fn () => (int) $data['weight'],
            'is_active' => fn () => $this->boolean($data['is_active']),
            'is_reviewed' => fn () => $this->boolean($data['is_reviewed']),
            'category' => fn () => $this->categoryId($data['category']),
        ];

        if ($existing !== null) {
            // Only touch the columns the file actually carries. A CSV of just
            // slug+url is a legitimate way to re-point a batch of entries, and
            // it must not blank out the descriptions and categories it never
            // mentioned.
            $attributes = [];

            foreach ($candidates as $column => $value) {
                if (in_array($column, $present, true)) {
                    $attributes[$column === 'category' ? 'category_id' : $column] = $value();
                }
            }

            // The slug is the public identity every already-shared link
            // resolves through, so it is never rewritten on an update.
            $existing->update($attributes);
            $link = $existing;
            $result['updated']++;
        } else {
            $attributes = [];

            foreach ($candidates as $column => $value) {
                $attributes[$column === 'category' ? 'category_id' : $column] = $value();
            }

            // A new entry with no is_active column should be live, not hidden.
            if (! in_array('is_active', $present, true)) {
                $attributes['is_active'] = true;
            }

            $link = Link::create($attributes + ['slug' => $this->uniqueSlug($slug, $data['title'])]);
            $result['created']++;
        }

        if (in_array('keywords', $present, true)) {
            $this->syncKeywords($link, $data['keywords']);
        }

        return null;
    }

    private function syncKeywords(Link $link, string $raw): void
    {
        if (trim($raw) === '') {
            return;
        }

        // Pipe-separated, because commas are the field delimiter and Indonesian
        // keywords ("berita, terkini") frequently contain them.
        $ids = collect(explode('|', $raw))
            ->map(fn (string $k) => trim($k))
            ->filter()
            ->map(fn (string $k) => Keyword::findOrCreateByName($k)->id)
            ->unique()
            ->all();

        $link->keywords()->syncWithoutDetaching($ids);

        // The observer fires on the link, not on the pivot, so the blob needs
        // an explicit rebuild once the keywords are attached.
        $this->observer->refreshSearchBlob($link->load('keywords'));
    }

    private function categoryId(string $name): ?int
    {
        $name = trim($name);

        return $name === ''
            ? null
            : Category::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name])->id;
    }

    private function uniqueSlug(string $preferred, string $title): string
    {
        $base = $preferred !== '' ? Str::slug($preferred) : Str::slug($title);
        $base = $base !== '' ? $base : 'entri';
        $slug = $base;

        // withTrashed: a soft-deleted entry still owns its slug. Handing it to
        // a new link would silently redirect old shared links somewhere else.
        while (Link::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }

    private function boolean(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'ya', 'yes', 'y', 'aktif'], true);
    }
}
