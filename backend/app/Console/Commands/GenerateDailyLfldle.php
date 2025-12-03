<?php

namespace App\Console\Commands;

use App\Services\DailyGameSelector;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyLfldle extends Command
{
    protected $signature = 'kcdle:generate-daily-lfldle {date?}';
    protected $description = 'Sélectionne les joueurs du jour pour LFLDLE';

    public function handle(DailyGameSelector $selector): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : today();

        $daily = $selector->selectForGame('lfldle', $date);
        $this->info(
            "lfldle → player_id={$daily->getAttribute('player_id')} pour {$daily->getAttribute('selected_for_date')->toDateString()}"
        );

        return self::SUCCESS;
    }
}
