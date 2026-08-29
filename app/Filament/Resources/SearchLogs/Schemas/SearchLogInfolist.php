<?php

namespace App\Filament\Resources\SearchLogs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SearchLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('query_raw'),
                TextEntry::make('query_normalized'),
                TextEntry::make('result_count')
                    ->numeric(),
                TextEntry::make('resolvedLink.title')
                    ->label('Resolved link')
                    ->placeholder('-'),
                TextEntry::make('source')
                    ->badge(),
                TextEntry::make('ip_hash')
                    ->placeholder('-'),
                TextEntry::make('user_agent')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
