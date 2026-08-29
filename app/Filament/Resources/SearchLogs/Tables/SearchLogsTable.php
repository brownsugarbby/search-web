<?php

namespace App\Filament\Resources\SearchLogs\Tables;

use App\Enums\TrafficSource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SearchLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('query_raw')->label('Kata kunci')->searchable()->wrap(),
                TextColumn::make('result_count')
                    ->label('Hasil')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Sumber')
                    ->badge()
                    ->formatStateUsing(fn (TrafficSource $state) => $state->label())
                    ->color(fn (TrafficSource $state) => $state === TrafficSource::Share ? 'info' : 'gray'),
                TextColumn::make('resolvedLink.title')->label('Diarahkan ke')->placeholder('-')->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('source')->label('Sumber')->options(
                    collect(TrafficSource::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all()
                ),
                Filter::make('zero')
                    ->label('Tanpa hasil')
                    ->query(fn (Builder $q) => $q->where('result_count', 0)),
            ]);
    }
}
