<?php

namespace App\Console\Commands;

use App\Services\DailyGameSelector;
use Illuminate\Console\Command;
use Carbon\Carbon;

class GenerateDailyGames extends Command
{
    protected $signature = 'kcdle:generate-daily-games {date?}';
    protected $description = 'Sélectionne les joueurs du jour pour KCDLE / LFLDLE / LECDLE';

    public function handle(DailyGameSelector $selector): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : today();

        foreach (['kcdle', 'lfldle', 'lecdle'] as $game) {
            $daily = $selector->selectForGame($game, $date);
            $this->info(
                "$game → player_id={$daily->getAttribute('player_id')} pour {$daily->getAttribute('selected_for_date')->toDateString()}"
            );
        }

        return self::SUCCESS;
    }
}

