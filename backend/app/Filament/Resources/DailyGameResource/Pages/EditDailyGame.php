<?php

namespace App\Filament\Resources\DailyGameResource\Pages;

use App\Filament\Resources\DailyGameResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDailyGame extends EditRecord
{
    protected static string $resource = DailyGameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
