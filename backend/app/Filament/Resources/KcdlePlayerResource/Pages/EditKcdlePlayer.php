<?php

namespace App\Filament\Resources\KcdlePlayerResource\Pages;

use App\Filament\Resources\KcdlePlayerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditKcdlePlayer extends EditRecord
{
    protected static string $resource = KcdlePlayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
