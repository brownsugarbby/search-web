<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * One command from a clean clone to a working site.
 *
 * The client installs this on their own server, so the setup that would
 * otherwise be a checklist of manual SQL and .env edits lives here instead.
 */
class AppInstall extends Command
{
    protected $signature = 'app:install {--force : Re-seed settings and pages even if they already exist}';

    protected $description = 'Create the admin user and seed default settings and pages';

    public function handle(): int
    {
        $this->components->info('Menyiapkan aplikasi / Setting up the application');

        $this->seedSettings();
        $this->seedPages();
        $this->createAdmin();

        $this->newLine();
        $this->components->info('Selesai. Panel admin: '.url('/'.config('search.panel_path')));

        return self::SUCCESS;
    }

    private function seedSettings(): void
    {
        $defaults = [
            'site_name' => config('app.name'),
            'meta_description' => 'Cari situs yang anda butuhkan dengan cepat.',
            'banner_text' => 'Gunakan internet dan layanan kami dengan bijak.',
            'banner_link' => '/peringatan',
            'hide_unreviewed' => false,
        ];

        foreach ($defaults as $key => $value) {
            if ($this->option('force') || Setting::query()->whereKey($key)->doesntExist()) {
                Setting::put($key, $value);
            }
        }

        $this->components->task('Pengaturan default / default settings');
    }

    private function seedPages(): void
    {
        $pages = [
            'peringatan' => [
                'title' => 'Peringatan',
                'meta_description' => 'Konten yang dilarang dicantumkan pada layanan ini.',
                // Long enough to be worth keeping out of PHP, and the client
                // edits it in the panel afterwards anyway.
                'body' => $this->content('peringatan'),
            ],
            'privacy-policy' => [
                'title' => 'Kebijakan Privasi',
                'meta_description' => 'Bagaimana kami memperlakukan data pengunjung.',
                'body' => '<p>Kami mencatat kata kunci yang dicari untuk memperbaiki layanan. '
                    .'Alamat IP tidak pernah disimpan dalam bentuk aslinya - hanya sebagai '
                    .'kode acak satu arah yang tidak dapat dikembalikan menjadi alamat asli.</p>',
            ],
        ];

        foreach ($pages as $slug => $attributes) {
            Page::query()->firstOrCreate(['slug' => $slug], $attributes);
        }

        $this->components->task('Halaman /peringatan dan /privacy-policy');
    }

    private function createAdmin(): void
    {
        if (User::query()->exists() && ! $this->option('force')) {
            $this->components->warn('Admin sudah ada, dilewati. (Admin already exists, skipped.)');

            return;
        }

        $name = text('Nama admin / Admin name', default: 'Admin', required: true);
        $email = text('Email admin', required: true, validate: fn (string $v) => filter_var($v, FILTER_VALIDATE_EMAIL)
            ? null
            : 'Email tidak valid.');

        $pass = password('Password', required: true, validate: fn (string $v) => strlen($v) >= 12
            ? null
            // This account can change every destination URL on the site, so a
            // short password is not an acceptable default for a hand-off.
            : 'Minimal 12 karakter.');

        User::query()->updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make($pass)],
        );

        $this->components->task('Admin '.$email);
    }

    /** Page bodies long enough to deserve their own file. */
    private function content(string $name): string
    {
        $path = database_path("content/{$name}.html");

        return is_file($path) ? (string) file_get_contents($path) : '';
    }
}
