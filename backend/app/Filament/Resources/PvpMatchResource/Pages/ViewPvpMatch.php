<?php

namespace App\Filament\Resources\PvpMatchResource\Pages;

use App\Filament\Resources\PvpMatchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

/**
 * View page for a single PvP match.
 */
class ViewPvpMatch extends ViewRecord
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
