<?php

namespace App\Console\Commands;

use App\Models\PendingGuess;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PurgePendingGuesses extends Command
{
    protected $signature = 'kcdle:purge-pending-guesses {--days=30}';
    protected $description = 'Supprime les guesses invités plus vieux que N jours';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = Carbon::now()->subDays($days);

        $count = PendingGuess::where('created_at', '<', $threshold)->delete();

        $this->info("Pending guesses purgés: {$count}");

        return self::SUCCESS;
    }
}
