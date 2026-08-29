<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Keyword;
use App\Models\Link;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A small, realistic catalog so the panel is not an empty table on first login.
 *
 * Pass --class=DemoSeeder with LINK_COUNT set to generate bulk rows instead,
 * for the 100k scale check described in docs/DEPLOY.md.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $categories = collect(['Berita', 'Belanja', 'Pemerintahan', 'Pendidikan', 'Keuangan'])
            ->mapWithKeys(fn (string $name) => [$name => Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )]);

        $catalog = [
            ['Portal Berita Nasional', 'https://www.antaranews.com', 'Berita', ['berita', 'berita nasional', 'antara']],
            ['Kompas', 'https://www.kompas.com', 'Berita', ['kompas', 'berita terkini']],
            ['Detik', 'https://www.detik.com', 'Berita', ['detik', 'berita cepat']],
            ['Tokopedia', 'https://www.tokopedia.com', 'Belanja', ['tokopedia', 'toko online', 'belanja']],
            ['Bukalapak', 'https://www.bukalapak.com', 'Belanja', ['bukalapak', 'belanja online']],
            ['Lapor', 'https://www.lapor.go.id', 'Pemerintahan', ['lapor', 'pengaduan', 'aduan']],
            ['Kemdikbud', 'https://www.kemdikbud.go.id', 'Pendidikan', ['kemdikbud', 'pendidikan', 'sekolah']],
            ['Bank Indonesia', 'https://www.bi.go.id', 'Keuangan', ['bank indonesia', 'bi', 'kurs']],
            ['Pajak', 'https://www.pajak.go.id', 'Keuangan', ['pajak', 'npwp', 'lapor pajak']],
            ['BPJS Kesehatan', 'https://www.bpjs-kesehatan.go.id', 'Pemerintahan', ['bpjs', 'bpjs kesehatan', 'jkn']],
        ];

        foreach ($catalog as [$title, $url, $category, $keywords]) {
            $link = Link::firstOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'url' => $url,
                    'description' => 'Akses cepat ke '.$title.'.',
                    'category_id' => $categories[$category]->id,
                    'is_active' => true,
                    'is_reviewed' => true,
                ],
            );

            $link->keywords()->syncWithoutDetaching(
                collect($keywords)->map(fn (string $k) => Keyword::findOrCreateByName($k)->id)->all()
            );

            // Keywords changed after the link was created, so the blob needs a
            // rebuild - the observer fires on the link, not on the pivot.
            $link->load('keywords');
            app(\App\Observers\LinkObserver::class)->refreshSearchBlob($link);
        }

        if ($bulk = (int) env('LINK_COUNT', 0)) {
            $this->command->info("Generating {$bulk} bulk links for the scale check...");

            Link::factory()->count($bulk)->create()->each(function (Link $link) {
                $link->keywords()->attach(Keyword::findOrCreateByName(Str::slug($link->title)));
            });

            $this->command->call('search:reindex');
        }

        $this->command->info('Seeded '.Link::count().' links.');
    }
}
