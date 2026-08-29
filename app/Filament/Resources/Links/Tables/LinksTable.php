<?php

namespace App\Filament\Resources\Links\Tables;

use App\Models\Link;
use App\Services\LinkImporter;
use App\Services\SearchService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Link $r) => $r->url, position: 'below')
                    ->wrap(),

                TextColumn::make('keywords_count')
                    ->label('Kata kunci')
                    ->counts('keywords')
                    ->badge()
                    // An entry with no keywords is only reachable through the
                    // fuzziest tier, so it is worth flagging at a glance.
                    ->color(fn ($state) => $state > 0 ? 'gray' : 'warning')
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('click_count')
                    ->label('Klik')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('share_open_count')
                    ->label('Dari tautan')
                    ->numeric()
                    ->sortable()
                    ->tooltip('Berapa kali entri ini dibuka lewat tautan yang dibagikan orang.'),

                TextColumn::make('weight')
                    ->label('Bobot')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')->label('Aktif')->boolean()->sortable(),
                IconColumn::make('is_reviewed')->label('Ditinjau')->boolean()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                /*
                 * Small files only - this runs inline in the request. The
                 * artisan command is the path for seeding a whole catalog.
                 */
                Action::make('import')
                    ->label('Impor CSV')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('gray')
                    ->modalDescription('Kolom: slug, title, url, description, keywords, category, weight, is_active, is_reviewed. Kata kunci dipisah dengan tanda |. Baris dengan slug yang sudah ada akan diperbarui, bukan digandakan.')
                    ->schema([
                        FileUpload::make('file')
                            ->label('Berkas CSV')
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv'])
                            ->storeFiles(false)
                            ->required()
                            ->helperText('Maksimal '.number_format(config('search.import_max_rows')).' baris. Untuk katalog besar gunakan: php artisan links:import berkas.csv'),
                    ])
                    ->action(function (array $data) {
                        $path = $data['file']->getRealPath();

                        if (self::rowCount($path) > config('search.import_max_rows')) {
                            Notification::make()
                                ->title('Berkas terlalu besar')
                                ->body('Gunakan `php artisan links:import` untuk berkas sebesar ini - impor lewat browser akan kehabisan waktu.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        $result = app(LinkImporter::class)->import($path);

                        Notification::make()
                            ->title("Dibuat {$result['created']}, diperbarui {$result['updated']}, dilewati {$result['skipped']}")
                            ->body($result['errors'] === [] ? null : implode(' | ', array_slice($result['errors'], 0, 5)))
                            ->color($result['skipped'] > 0 ? 'warning' : 'success')
                            ->persistent()
                            ->send();
                    }),

                /*
                 * Round-trips with the importer: the export carries slugs, so
                 * editing the file and importing it back updates the same rows
                 * instead of creating duplicates.
                 */
                Action::make('export')
                    ->label('Ekspor CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(fn () => self::exportCsv()),
            ])
            ->filters([
                SelectFilter::make('category')->relationship('category', 'name')->label('Kategori'),
                TernaryFilter::make('is_active')->label('Aktif'),
                TernaryFilter::make('is_reviewed')->label('Sudah ditinjau'),
                Filter::make('orphaned')
                    ->label('Tanpa kata kunci')
                    ->query(fn (Builder $q) => $q->doesntHave('keywords')),
            ])
            ->recordActions([
                /*
                 * The phase-1 way to distribute a share link.
                 *
                 * The public share button lives beside a search result, and the
                 * results page is switched off for now - so until it is on, this
                 * is where share URLs come from.
                 */
                Action::make('copyShareUrl')
                    ->label('Salin tautan')
                    ->icon('heroicon-o-share')
                    ->color('gray')
                    ->action(function (Link $record) {
                        Notification::make()
                            ->title('Tautan untuk dibagikan')
                            ->body($record->shareUrl())
                            ->persistent()
                            ->success()
                            ->send();
                    }),

                /*
                 * Answers "what will a visitor actually get?" without leaving
                 * the panel - including which entry a share of this keyword
                 * would resolve to right now.
                 */
                Action::make('testSearch')
                    ->label('Uji pencarian')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('gray')
                    ->schema([
                        TextInput::make('query')->label('Kata kunci')->required(),
                    ])
                    ->action(function (array $data) {
                        $results = app(SearchService::class)->search($data['query']);

                        Notification::make()
                            ->title($results->isEmpty()
                                ? 'Tidak ada hasil'
                                : 'Hasil pertama: '.$results->first()->title)
                            ->body($results->isEmpty()
                                ? 'Kata kunci ini belum mengarah ke mana pun.'
                                : $results->pluck('title')->take(5)->implode(' · '))
                            ->color($results->isEmpty() ? 'warning' : 'success')
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Aktifkan')
                        ->icon('heroicon-o-check-circle')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate')
                        ->label('Nonaktifkan')
                        ->icon('heroicon-o-x-circle')
                        ->requiresConfirmation()
                        ->modalDescription('Entri yang dinonaktifkan hilang dari pencarian dan dari tautan yang sudah dibagikan orang.')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /** Count data rows without loading the file into memory. */
    private static function rowCount(string $path): int
    {
        $handle = fopen($path, 'rb');
        $count = 0;

        while (fgets($handle) !== false) {
            $count++;
        }

        fclose($handle);

        return max(0, $count - 1); // discount the header
    }

    private static function exportCsv(): StreamedResponse
    {
        $name = 'tautan-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'wb');

            fputcsv($out, [
                'slug', 'title', 'url', 'description', 'keywords',
                'category', 'weight', 'is_active', 'is_reviewed',
            ], escape: '\\');

            // Chunked, so exporting 100k rows does not build the whole file in
            // memory before the download starts.
            Link::with(['keywords', 'category'])->chunk(500, function ($links) use ($out) {
                foreach ($links as $link) {
                    fputcsv($out, [
                        $link->slug,
                        $link->title,
                        $link->url,
                        $link->description,
                        $link->keywords->pluck('keyword')->implode('|'),
                        $link->category?->name,
                        $link->weight,
                        $link->is_active ? 1 : 0,
                        $link->is_reviewed ? 1 : 0,
                    ], escape: '\\');
                }
            });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
