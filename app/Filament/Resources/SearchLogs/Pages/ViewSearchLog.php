<?php

namespace App\Filament\Resources\SearchLogs\Pages;

use App\Filament\Resources\SearchLogs\SearchLogResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSearchLog extends ViewRecord
{
    protected static string $resource = SearchLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
