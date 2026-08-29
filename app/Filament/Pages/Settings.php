<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

/**
 * Site-wide settings, stored as key/value rows rather than in .env.
 *
 * These are things the client changes themselves - the site name, the advisory
 * banner, the logo. Putting them in .env would mean an SSH session and a
 * config:cache for every wording tweak.
 */
class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static string|UnitEnum|null $navigationGroup = 'Situs';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /** Keys the form owns, with the default used when nothing is stored yet. */
    private const KEYS = [
        'site_name' => '',
        'meta_description' => '',
        'banner_text' => '',
        'banner_link' => '',
        'logo_url' => null,
        'favicon_url' => null,
    ];

    public function mount(): void
    {
        $this->form->fill(
            collect(self::KEYS)
                ->map(fn ($default, $key) => Setting::get($key, $default))
                ->all()
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas situs')
                    ->columns(2)
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Nama situs')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('meta_description')
                            ->label('Deskripsi meta')
                            ->maxLength(160)
                            ->helperText('Muncul di hasil mesin pencari. Maksimal 160 karakter.'),

                        FileUpload::make('logo_url')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->maxSize(1024),

                        FileUpload::make('favicon_url')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->maxSize(256),
                    ]),

                Section::make('Peringatan di atas halaman')
                    ->description('Ditampilkan di setiap halaman. Kosongkan untuk menyembunyikannya.')
                    ->columns(2)
                    ->schema([
                        Textarea::make('banner_text')
                            ->label('Teks')
                            ->rows(2)
                            ->maxLength(255),

                        TextInput::make('banner_link')
                            ->label('Tautan "Baca disini"')
                            ->maxLength(255)
                            ->helperText('Contoh: /peringatan'),
                    ]),

                /*
                 * "Keamanan katalog" (the hide_unreviewed toggle) is off the
                 * page for now. The setting itself still works - Link::query()
                 * reads it - so it is also left out of self::KEYS: saving this
                 * form must not blank a value it no longer offers to edit.
                 * Restoring it means re-adding the section and the key together.
                 */
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach (array_keys(self::KEYS) as $key) {
            $value = $state[$key] ?? null;

            // FileUpload hands back the stored path; the views want a URL.
            if (in_array($key, ['logo_url', 'favicon_url'], true) && filled($value)) {
                $value = Storage::disk('public')->url($value);
            }

            Setting::put($key, $value ?? '');
        }

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Simpan')->submit('save')];
    }

    public function getTitle(): string
    {
        return 'Pengaturan';
    }
}
