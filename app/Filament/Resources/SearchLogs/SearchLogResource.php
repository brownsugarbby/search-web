<?php

namespace App\Filament\Resources\SearchLogs;

use App\Filament\Resources\SearchLogs\Pages\ListSearchLogs;
use App\Filament\Resources\SearchLogs\Tables\SearchLogsTable;
use App\Models\SearchLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Read-only. Search history is evidence of what visitors actually asked for;
 * editing it would only make the reports lie.
 */
class SearchLogResource extends Resource
{
    protected static ?string $model = SearchLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Riwayat Pencarian';

    protected static string|UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 1;

    public static function table(Table $table): Table
    {
        return SearchLogsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListSearchLogs::route('/')];
    }
}
