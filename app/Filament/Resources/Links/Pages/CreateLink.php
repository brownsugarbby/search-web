<?php

namespace App\Filament\Resources\Links\Pages;

use App\Filament\Resources\Links\LinkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLink extends CreateRecord
{
    protected static string $resource = LinkResource::class;

    /**
     * The "Peringkat & status" section is hidden while creating, so its fields
     * never reach the form state. Supply the values a new entry starts with
     * here; they stay editable afterwards from the edit form.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...$data,
            'weight' => 0,
            'is_active' => true,
            'is_reviewed' => false,
        ];
    }
}
