<?php

namespace App\Filament\Pages;

use App\Enums\TrafficSource;
use App\Filament\Resources\Links\LinkResource;
use App\Models\SearchLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * What visitors asked for and did not get.
 *
 * This is the screen that makes the catalog improve on its own: instead of
 * guessing what to add next, the admin works a list ordered by how many real
 * people wanted it.
 *
 * Queries that arrived through a shared link are surfaced first. Someone is
 * actively handing that URL to other people, so every further open is another
 * person hitting the same dead end.
 */
class ZeroResultQueries extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Pencarian Tanpa Hasil';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.pages.zero-result-queries';

    public function getTitle(): string
    {
        return 'Pencarian Tanpa Hasil';
    }

    /**
     * These rows are aggregates, not records - the query groups by keyword and
     * has no id to key on. The keyword itself is the identity.
     */
    public function getTableRecordKey(Model|array $record): string
    {
        return (string) (is_array($record) ? $record['query_normalized'] : $record->query_normalized);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SearchLog::query()
                    ->select('query_normalized')
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw('MAX(created_at) as last_seen')
                    // MAX() over an enum column stored as a string: 'share'
                    // sorts above 'lucky' and 'direct', which is exactly the
                    // priority we want, but state it explicitly rather than
                    // relying on the alphabet.
                    ->selectRaw("MAX(source = ?) as from_share", [TrafficSource::Share->value])
                    ->where('result_count', 0)
                    ->groupBy('query_normalized')
            )
            ->columns([
                TextColumn::make('query_normalized')->label('Kata kunci')->searchable()->wrap(),

                TextColumn::make('total')
                    ->label('Dicari')
                    ->badge()
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('from_share')
                    ->label('Tautan mati')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Ya' : '-')
                    ->color(fn ($state) => $state ? 'warning' : 'gray')
                    ->tooltip('Ada orang yang membagikan tautan ini dan penerimanya tidak menemukan apa pun.')
                    ->sortable(),

                TextColumn::make('last_seen')->label('Terakhir')->dateTime('d M Y H:i')->sortable(),
            ])
            // Dead shared links first, then sheer volume.
            ->defaultSort('from_share', 'desc')
            // These rows are GROUP BY aggregates. Filament otherwise appends
            // search_logs.id as a sort tiebreaker, which MySQL rejects under
            // ONLY_FULL_GROUP_BY because it is not in the GROUP BY clause.
            ->defaultKeySort(false)
            ->recordActions([
                Action::make('createLink')
                    ->label('Buat entri')
                    ->icon('heroicon-o-plus')
                    ->url(fn ($record) => LinkResource::getUrl('create', [
                        'keyword' => $record->query_normalized,
                    ])),
            ]);
    }
}
