<?php

namespace App\Console\Commands;

use App\Services\DailyGameSelector;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyKcdle extends Command
{
    protected $signature = 'kcdle:generate-daily-kcdle {date?}';
    protected $description = 'Sélectionne les joueurs du jour pour KCDLE';

    public function handle(DailyGameSelector $selector): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : today();

        $daily = $selector->selectForGame('kcdle', $date);
        $this->info(
            "kcdle → player_id={$daily->getAttribute('player_id')} pour {$daily->getAttribute('selected_for_date')->toDateString()}"
        );

        return self::SUCCESS;
    }
}
