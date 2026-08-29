<?php

namespace App\Filament\Widgets;

use App\Models\Link;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopLinks extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    protected static ?string $heading = 'Entri paling sering dibuka';

    public function table(Table $table): Table
    {
        return $table
            ->query(Link::query()->public()->orderByDesc('click_count'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('title')->label('Judul')->wrap()->limit(40),
                TextColumn::make('click_count')->label('Klik')->badge()->color('gray'),
                TextColumn::make('share_open_count')
                    ->label('Dari tautan')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'info' : 'gray')
                    ->tooltip('Dibuka lewat tautan yang dibagikan orang.'),
            ]);
    }
}
