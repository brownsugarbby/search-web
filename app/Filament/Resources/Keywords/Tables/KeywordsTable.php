<?php

namespace App\Filament\Resources\Keywords\Tables;

use App\Models\Keyword;
use App\Observers\LinkObserver;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KeywordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('keyword')->label('Kata kunci')->searchable()->sortable(),
                TextColumn::make('keyword_normalized')
                    ->label('Bentuk pencocokan')
                    ->color('gray')
                    ->size('sm')
                    ->tooltip('Bentuk yang benar-benar dicocokkan dengan ketikan pengunjung.')
                    ->toggleable(),
                TextColumn::make('links_count')
                    ->label('Entri')
                    ->counts('links')
                    ->badge()
                    // A keyword attached to nothing is dead weight: it will
                    // never match, and it clutters the typeahead source.
                    ->color(fn ($state) => $state > 0 ? 'gray' : 'danger')
                    ->sortable(),
            ])
            ->defaultSort('keyword')
            ->filters([
                Filter::make('orphaned')
                    ->label('Tidak terpakai')
                    ->query(fn (Builder $q) => $q->doesntHave('links')),
            ])
            ->recordActions([
                /*
                 * Near-duplicates are inevitable once more than one person is
                 * entering keywords ("bpjs", "b p j s"). Merging moves the
                 * links across and deletes the loser, so ranking is not split
                 * between two rows that mean the same thing.
                 */
                Action::make('merge')
                    ->label('Gabungkan')
                    ->icon('heroicon-o-arrows-pointing-in')
                    ->color('gray')
                    ->schema([
                        Select::make('target_id')
                            ->label('Gabungkan ke')
                            ->options(fn (Keyword $record) => Keyword::query()
                                ->whereKeyNot($record->getKey())
                                ->orderBy('keyword')
                                ->pluck('keyword', 'id'))
                            ->searchable()
                            ->required(),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription('Semua entri dipindahkan ke kata kunci tujuan, lalu kata kunci ini dihapus.')
                    ->action(function (Keyword $record, array $data) {
                        $target = Keyword::findOrFail($data['target_id']);

                        $moved = $record->links()->pluck('links.id');
                        $target->links()->syncWithoutDetaching($moved->all());

                        // Both sides change: the losing keyword leaves every
                        // blob it was folded into, the winner joins them.
                        $observer = app(LinkObserver::class);

                        foreach ($record->links()->with('keywords')->get() as $link) {
                            $link->keywords()->detach($record->getKey());
                            $observer->refreshSearchBlob($link->load('keywords'));
                        }

                        $record->delete();

                        Notification::make()
                            ->title("{$moved->count()} entri dipindahkan ke \"{$target->keyword}\"")
                            ->success()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
