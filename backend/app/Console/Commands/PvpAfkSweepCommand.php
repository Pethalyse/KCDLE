<?php

namespace App\Console\Commands;

use App\Services\Pvp\PvpAfkSweepService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Runs an AFK sweep over active PvP matches.
 *
 * Intended to be scheduled to run frequently (e.g., every minute).
 */
class PvpAfkSweepCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'pvp:afk-sweep';

    /**
     * @var string
     */
    protected $description = 'Sweep active PvP matches to resolve AFK participants.';

    /**
     * Execute the console command.
     *
     * @param PvpAfkSweepService $sweeper AFK sweep service.
     *
     * @return int
     * @throws Throwable
     */
    public function handle(PvpAfkSweepService $sweeper): int
    {
        $result = $sweeper->sweep();
        $this->info('checked=' . $result['checked'] . ' forfeited=' . $result['forfeited']);
        return self::SUCCESS;
    }
}
