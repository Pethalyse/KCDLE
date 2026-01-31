<?php

namespace App\Filament\Resources\PvpMatchResource\Pages;

use App\Filament\Resources\PvpMatchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

/**
 * List page for PvP matches.
 */
class ListPvpMatches extends ListRecords
{
    protected static string $resource = PvpMatchResource::class;

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
