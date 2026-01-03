<?php

namespace App\Console\Commands;

use App\Models\PvpMatch;
use App\Services\Pvp\PvpMatchEngineService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

/**
 * Runs passive ticks for active PvP matches.
 *
 * This command is intended to be scheduled every second to keep time-driven rounds
 * fluid without requiring any client polling cadence.
 */
class PvpPassiveTickCommand extends Command
{
    protected $signature = 'pvp:passive-tick {--limit=200}';
    protected $description = 'Tick active PvP matches for time-driven rounds (ex: reveal_race).';

    public function __construct(private readonly PvpMatchEngineService $engine)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     * @throws Throwable
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $limit = max(1, min($limit, 2000));

        $matches = PvpMatch::query()
            ->where('status', 'active')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'current_round', 'rounds', 'state']);

        $scanned = 0;
        $ticked = 0;

        foreach ($matches as $m) {
            $scanned++;

            $state = is_array($m->state) ? $m->state : [];
            $roundIndex = (int) $m->current_round;
            $roundType = (string) (Arr::get($state, 'round_type') ?? ($m->rounds[$roundIndex - 1] ?? ''));

            if ($roundType === '') {
                continue;
            }

            if ($roundType !== 'reveal_race') {
                continue;
            }

            $res = $this->engine->passiveTick((int) $m->id);
            if (! empty($res['ticked'])) {
                $ticked++;
            }
        }

        $this->line("pvp:passive-tick scanned={$scanned} ticked={$ticked}");

        return self::SUCCESS;
    }
}
