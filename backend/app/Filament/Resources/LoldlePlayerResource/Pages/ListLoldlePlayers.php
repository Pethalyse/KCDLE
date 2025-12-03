<?php

namespace App\Filament\Resources\LeagueMembershipResource\Pages;

use App\Filament\Resources\LoldlePlayerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLoldlePlayers extends ListRecords
{
    protected static string $resource = LoldlePlayerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
