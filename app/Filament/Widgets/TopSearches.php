<?php

namespace App\Filament\Widgets;

use App\Models\SearchLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopSearches extends TableWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Pencarian terbanyak (7 hari)';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                SearchLog::query()
                    ->select('query_normalized')
                    ->selectRaw('COUNT(*) as total')
                    ->selectRaw('SUM(result_count = 0) as misses')
                    ->where('created_at', '>=', now()->subDays(7))
                    ->groupBy('query_normalized')
                    ->orderByDesc('total')
            )
            // Aggregate rows: no id to key on, and Filament's primary-key sort
            // tiebreaker would break the GROUP BY under ONLY_FULL_GROUP_BY.
            ->defaultKeySort(false)
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('query_normalized')->label('Kata kunci')->wrap(),
                TextColumn::make('total')->label('Jumlah')->badge(),
                TextColumn::make('misses')
                    ->label('Gagal')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
            ]);
    }

    public function getTableRecordKey(\Illuminate\Database\Eloquent\Model|array $record): string
    {
        return (string) (is_array($record) ? $record['query_normalized'] : $record->query_normalized);
    }
}
