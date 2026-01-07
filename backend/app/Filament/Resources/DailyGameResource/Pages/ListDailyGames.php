<?php

namespace App\Filament\Resources\DailyGameResource\Pages;

use App\Filament\Resources\DailyGameResource;
use App\Services\DailyGameSelector;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDailyGames extends ListRecords
{
    protected static string $resource = DailyGameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generateDaily')
                ->label('Générer les joueurs du jour')
                ->icon('heroicon-o-play')
                ->form([
                    Forms\Components\DatePicker::make('date')
                        ->label('Date')
                        ->default(today())
                        ->required(),
                    Forms\Components\CheckboxList::make('games')
                        ->label('Jeux')
                        ->options([
                            'kcdle'  => 'KCDLE',
                            'lfldle' => 'LFLDLE',
                            'lecdle' => 'LECDLE',
                        ])
                        ->default(['kcdle', 'lfldle', 'lecdle'])
                        ->required(),
                ])
                ->action(function (array $data, DailyGameSelector $selector) {
                    $date = Carbon::parse($data['date']);
                    $games = $data['games'] ?? [];

                    foreach ($games as $game) {
                        $selector->selectForGame($game, $date);
                    }

                    Notification::make()
                        ->success()
                        ->title('Daily générés')
                        ->body('Daily générés pour ' . $date->toDateString())
                        ->send();
                }),
        ];
    }
}
