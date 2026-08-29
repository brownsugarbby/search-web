<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),

                TextColumn::make('user.name')->label('Oleh')->placeholder('sistem'),

                TextColumn::make('event')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'created' => 'Dibuat',
                        'updated' => 'Diubah',
                        'deleted' => 'Dihapus',
                        'restored' => 'Dipulihkan',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('new_values.title')
                    ->label('Entri')
                    ->placeholder('-')
                    ->formatStateUsing(fn ($state, AuditLog $record) => $state
                        ?? $record->old_values['title']
                        ?? '#'.$record->auditable_id)
                    ->wrap(),

                TextColumn::make('changed')
                    ->label('Kolom')
                    ->state(fn (AuditLog $r) => implode(', ', array_keys($r->new_values ?? $r->old_values ?? [])))
                    ->limit(50)
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('event')->label('Aksi')->options([
                    'created' => 'Dibuat',
                    'updated' => 'Diubah',
                    'deleted' => 'Dihapus',
                    'restored' => 'Dipulihkan',
                ]),
            ])
            ->recordActions([
                Action::make('diff')
                    ->label('Lihat')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Perubahan')
                    ->modalSubmitAction(false)
                    ->modalContent(fn (AuditLog $record) => new HtmlString(self::renderDiff($record)))
                    ->modalCancelActionLabel('Tutup'),
            ]);
    }

    /** Before and after, side by side, escaped. */
    private static function renderDiff(AuditLog $record): string
    {
        $old = $record->old_values ?? [];
        $new = $record->new_values ?? [];
        $keys = array_unique([...array_keys($old), ...array_keys($new)]);

        if ($keys === []) {
            return '<p class="text-sm text-gray-500">Tidak ada rincian.</p>';
        }

        $rows = '';

        foreach ($keys as $key) {
            $before = $old[$key] ?? null;
            $after = $new[$key] ?? null;

            $rows .= '<tr class="border-b border-gray-100 dark:border-gray-800 align-top">'
                .'<td class="py-2 pr-3 font-medium">'.e($key).'</td>'
                .'<td class="py-2 pr-3 text-danger-600 line-through">'.e(self::stringify($before)).'</td>'
                .'<td class="py-2 text-success-600">'.e(self::stringify($after)).'</td>'
                .'</tr>';
        }

        return '<div class="overflow-x-auto"><table class="w-full text-sm">'
            .'<thead><tr class="text-left text-xs uppercase text-gray-400">'
            .'<th class="pb-2 pr-3">Kolom</th><th class="pb-2 pr-3">Sebelum</th><th class="pb-2">Sesudah</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>';
    }

    private static function stringify(mixed $value): string
    {
        return match (true) {
            $value === null => '-',
            is_bool($value) => $value ? 'ya' : 'tidak',
            is_array($value) => json_encode($value, JSON_UNESCAPED_SLASHES) ?: '-',
            default => (string) $value,
        };
    }
}
