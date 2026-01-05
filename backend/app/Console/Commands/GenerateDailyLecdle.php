<?php

namespace App\Console\Commands;

use App\Services\DailyGameSelector;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailyLecdle extends Command
{
    protected $signature = 'kcdle:generate-daily-lecdle {date?}';
    protected $description = 'Sélectionne les joueurs du jour pour LECDLE';

    public function handle(DailyGameSelector $selector): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : today();

        $daily = $selector->selectForGame('lecdle', $date);
        $this->info(
            "lecdle → player_id={$daily->getAttribute('player_id')} pour {$daily->getAttribute('selected_for_date')->toDateString()}"
        );

        return self::SUCCESS;
    }
}
