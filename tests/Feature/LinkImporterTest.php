<?php

use App\Models\Category;
use App\Models\Link;
use App\Services\LinkImporter;
use App\Services\SearchService;

function csv(string $contents): string
{
    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, $contents);

    return $path;
}

beforeEach(fn () => $this->importer = app(LinkImporter::class));

it('creates links with keywords and categories', function () {
    $result = $this->importer->import(csv(<<<'CSV'
    title,url,description,keywords,category
    Traveloka,https://www.traveloka.com,Tiket dan hotel,tiket pesawat|hotel,Perjalanan
    CSV));

    expect($result['created'])->toBe(1)
        ->and($result['errors'])->toBe([]);

    $link = Link::where('title', 'Traveloka')->first();

    expect($link->keywords)->toHaveCount(2)
        ->and($link->category->name)->toBe('Perjalanan')
        ->and(app(SearchService::class)->firstMatch('tiket pesawat')?->id)->toBe($link->id);
});

it('generates a slug when the file does not supply one', function () {
    $this->importer->import(csv("title,url\nBank Contoh,https://contoh.example\n"));

    expect(Link::where('title', 'Bank Contoh')->first()->slug)->toBe('bank-contoh');
});

it('updates an existing entry rather than duplicating it', function () {
    $this->importer->import(csv("slug,title,url\ntoko,Toko Lama,https://lama.example\n"));
    $result = $this->importer->import(csv("slug,title,url\ntoko,Toko Baru,https://baru.example\n"));

    expect($result['updated'])->toBe(1)
        ->and($result['created'])->toBe(0)
        ->and(Link::where('slug', 'toko')->count())->toBe(1)
        ->and(Link::where('slug', 'toko')->first()->title)->toBe('Toko Baru');
});

it('leaves out columns the file does not carry', function () {
    $category = Category::create(['name' => 'Belanja', 'slug' => 'belanja']);
    $link = Link::factory()->create([
        'slug' => 'toko',
        'description' => 'Deskripsi asli',
        'category_id' => $category->id,
        'weight' => 7,
    ]);
    $link->keywords()->attach(\App\Models\Keyword::findOrCreateByName('toko lama'));

    // Re-point the URL and nothing else.
    $this->importer->import(csv("slug,url\ntoko,https://baru.example\n"));

    $link->refresh();

    // Everything the file never mentioned has to survive untouched.
    expect($link->url)->toBe('https://baru.example')
        ->and($link->description)->toBe('Deskripsi asli')
        ->and($link->category_id)->toBe($category->id)
        ->and($link->weight)->toBe(7)
        ->and($link->keywords)->toHaveCount(1);
});

it('never rewrites the slug of an existing entry', function () {
    $link = Link::factory()->create(['slug' => 'asli', 'title' => 'Judul']);

    $this->importer->import(csv("slug,title,url\nasli,Judul Baru,https://baru.example\n"));

    // Shared links resolve through the slug; changing it would break every one
    // already in circulation.
    expect($link->fresh()->slug)->toBe('asli');
});

it('never hands a soft-deleted slug to a new entry', function () {
    $old = Link::factory()->create(['slug' => 'tokopedia']);
    $old->delete();

    $this->importer->import(csv("title,url\nTokopedia,https://tokopedia.example\n"));

    expect(Link::where('title', 'Tokopedia')->first()->slug)->not->toBe('tokopedia');
});

it('reports bad rows by line number and keeps going', function () {
    $result = $this->importer->import(csv(<<<'CSV'
    title,url
    Bagus,https://bagus.example
    Rusak,bukan-url
    Bagus Lagi,https://lagi.example
    CSV));

    expect($result['created'])->toBe(2)
        ->and($result['skipped'])->toBe(1)
        ->and($result['errors'][0])->toContain('Baris 3');
});

it('honours the destination blocklist', function () {
    config(['search.blocked_hosts' => ['jahat.example']]);

    $result = $this->importer->import(csv("title,url\nJahat,https://sub.jahat.example\n"));

    // Subdomains of a blocked host are blocked too.
    expect($result['created'])->toBe(0)
        ->and($result['skipped'])->toBe(1);
});

it('writes nothing on a dry run', function () {
    $result = $this->importer->import(csv("title,url\nUji,https://uji.example\n"), dryRun: true);

    expect($result['created'])->toBe(1)
        ->and(Link::where('title', 'Uji')->exists())->toBeFalse();
});

it('survives a spreadsheet byte order mark', function () {
    // Excel writes a BOM, which would otherwise make the first column
    // "\u{FEFF}title" and the entire file unreadable.
    $result = $this->importer->import(csv("\u{FEFF}title,url\nDari Excel,https://excel.example\n"));

    expect($result['created'])->toBe(1);
});

it('rejects a file with no identifying column', function () {
    $result = $this->importer->import(csv("url,description\nhttps://a.example,tanpa judul\n"));

    expect($result['created'])->toBe(0)
        ->and($result['errors'][0])->toContain('slug');
});
