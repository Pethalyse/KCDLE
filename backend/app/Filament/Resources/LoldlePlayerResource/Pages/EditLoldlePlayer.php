<?php

namespace App\Filament\Resources\LoldlePlayerResource\Pages;

use App\Filament\Resources\LoldlePlayerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLoldlePlayer extends EditRecord
{
    protected static string $resource = LoldlePlayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
