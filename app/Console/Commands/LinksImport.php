<?php

namespace App\Console\Commands;

use App\Services\LinkImporter;
use Illuminate\Console\Command;

/**
 * The bulk path for seeding a catalog.
 *
 * The panel has an upload action too, but it is capped: a 100k-row import
 * cannot finish inside an HTTP request, and the queue ships as `sync` so there
 * is no worker to hand it to. Over SSH there is no timeout to fight.
 */
class LinksImport extends Command
{
    protected $signature = 'links:import
        {file : Path to the CSV file}
        {--dry-run : Report what would happen without writing anything}';

    protected $description = 'Import links from a CSV file';

    public function handle(LinkImporter $importer): int
    {
        $file = $this->argument('file');

        if (! is_file($file)) {
            $this->components->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->components->warn('Dry run - tidak ada yang ditulis ke database.');
        }

        $bar = $this->output->createProgressBar();
        $bar->start();

        $result = $importer->import($file, $dryRun, fn () => $bar->advance());

        $bar->finish();
        $this->newLine(2);

        $this->components->twoColumnDetail('Dibuat / created', (string) $result['created']);
        $this->components->twoColumnDetail('Diperbarui / updated', (string) $result['updated']);
        $this->components->twoColumnDetail('Dilewati / skipped', (string) $result['skipped']);

        foreach ($result['errors'] as $error) {
            $this->components->warn($error);
        }

        if (! $dryRun && ($result['created'] || $result['updated'])) {
            $this->components->info('Selesai. Jalankan `php artisan search:reindex` bila hasil pencarian terlihat tidak lengkap.');
        }

        return $result['skipped'] > 0 && $result['created'] === 0 && $result['updated'] === 0
            ? self::FAILURE
            : self::SUCCESS;
    }
}
