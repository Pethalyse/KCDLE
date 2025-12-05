<?php

namespace App\Filament\Resources\KcdlePlayerResource\Pages;

use App\Filament\Resources\KcdlePlayerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKcdlePlayers extends ListRecords
{
    protected static string $resource = KcdlePlayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
