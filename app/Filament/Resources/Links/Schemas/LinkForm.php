<?php

namespace App\Filament\Resources\Links\Schemas;

use App\Models\Keyword;
use App\Rules\AllowedDestination;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class LinkForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entri')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Judul')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                // Only auto-fill on create. Regenerating the slug
                                // on edit would silently break every link already
                                // shared for this entry.
                                if ($operation === 'create') {
                                    $set('slug', Str::slug($state).'-'.Str::lower(Str::random(4)));
                                }
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->dehydrated()
                            ->helperText('Identitas publik entri ini. Tidak bisa diubah - tautan yang sudah dibagikan orang lain memakai slug ini.'),

                        Textarea::make('url')
                            ->label('URL tujuan')
                            ->required()
                            ->rows(2)
                            // Textarea has no ->url(); the rule does the same job.
                            ->rules(['url', new AllowedDestination])
                            ->columnSpanFull()
                            ->helperText('Boleh diubah kapan saja - tautan yang sudah dibagikan akan otomatis mengikuti ke alamat baru.'),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->rows(2)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ]),

                Section::make('Kata kunci')
                    ->description('Kata kunci yang diketik pengunjung untuk menemukan entri ini.')
                    ->schema([
                        Select::make('keywords')
                            ->label('Kata kunci')
                            ->relationship('keywords', 'keyword')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->createOptionForm([
                                TextInput::make('keyword')->required()->maxLength(255),
                            ])
                            // Route creation through the model so the normalised
                            // form is always produced by QueryNormalizer.
                            ->createOptionUsing(fn (array $data) => Keyword::findOrCreateByName($data['keyword'])->getKey())
                            ->columnSpanFull(),
                    ]),

                // Kept off the create form to keep first entry short; the
                // defaults for these live in CreateLink::mutateFormDataBeforeCreate().
                Section::make('Peringkat & status')
                    ->hiddenOn('create')
                    ->columns(3)
                    ->schema([
                        TextInput::make('weight')
                            ->label('Bobot')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Makin tinggi, makin diutamakan.'),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText('Nonaktif = hilang dari pencarian dan dari tautan yang dibagikan.'),

                        Toggle::make('is_reviewed')
                            ->label('Sudah ditinjau')
                            ->default(false),

                        Select::make('category_id')
                            ->label('Kategori')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),

                        Textarea::make('notes')
                            ->label('Catatan internal')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
