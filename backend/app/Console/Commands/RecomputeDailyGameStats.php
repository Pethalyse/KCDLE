<?php

namespace App\Console\Commands;

use App\Models\DailyGame;
use App\Models\PendingGuess;
use App\Models\UserGameResult;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recompute daily statistics (solvers_count / total_guesses) from source tables.
 *
 * This command is intended to repair inconsistent or inflated values in the
 * `daily_games` table by recalculating:
 * - solvers_count: number of authenticated winners + anonymous winners
 * - total_guesses: sum of guesses needed to solve (authenticated) + anonymous
 *
 * It uses:
 * - `user_game_results` (where won_at is not null) for authenticated users
 * - `pending_guesses` (first correct guess per anon_key) for anonymous users
 */
class RecomputeDailyGameStats extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'kcdle:recompute-daily-stats {--game=} {--date=} {--from=} {--to=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recompute daily_games statistics from user_game_results and pending_guesses.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $game = $this->option('game');
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        $query = DailyGame::query();

        if (is_string($game) && trim($game) !== '') {
            $query->where('game', trim($game));
        }

        if (is_string($date) && trim($date) !== '') {
            try {
                $target = Carbon::createFromFormat('Y-m-d', trim($date))->startOfDay();
            } catch (\Throwable) {
                $this->error('Invalid --date, expected YYYY-MM-DD.');
                return self::FAILURE;
            }

            $query->whereDate('selected_for_date', $target);
        } else {
            if (is_string($from) && trim($from) !== '') {
                try {
                    $start = Carbon::createFromFormat('Y-m-d', trim($from))->startOfDay();
                } catch (\Throwable) {
                    $this->error('Invalid --from, expected YYYY-MM-DD.');
                    return self::FAILURE;
                }
                $query->whereDate('selected_for_date', '>=', $start);
            }

            if (is_string($to) && trim($to) !== '') {
                try {
                    $end = Carbon::createFromFormat('Y-m-d', trim($to))->endOfDay();
                } catch (\Throwable) {
                    $this->error('Invalid --to, expected YYYY-MM-DD.');
                    return self::FAILURE;
                }
                $query->whereDate('selected_for_date', '<=', $end);
            }
        }

        $dailies = $query->orderBy('selected_for_date')->get();

        if ($dailies->isEmpty()) {
            $this->info('No daily games matched the filters.');
            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($dailies as $daily) {
            $dailyId = (int) $daily->getAttribute('id');

            $authSolvers = UserGameResult::query()
                ->where('daily_game_id', $dailyId)
                ->whereNotNull('won_at')
                ->count();

            $authTotalGuesses = (int) UserGameResult::query()
                ->where('daily_game_id', $dailyId)
                ->whereNotNull('won_at')
                ->sum('guesses_count');

            $anonAgg = PendingGuess::query()
                ->where('daily_game_id', $dailyId)
                ->where('correct', true)
                ->select('anon_key', DB::raw('MIN(guess_order) as first_correct_order'))
                ->groupBy('anon_key')
                ->get();

            $anonSolvers = $anonAgg->count();
            $anonTotalGuesses = (int) $anonAgg->sum('first_correct_order');

            $solversCount = $authSolvers + $anonSolvers;
            $totalGuesses = $authTotalGuesses + $anonTotalGuesses;

            $daily->setAttribute('solvers_count', $solversCount);
            $daily->setAttribute('total_guesses', $totalGuesses);
            $daily->save();

            $updated++;

            $this->line(sprintf(
                '%s %s -> solvers=%d (auth=%d anon=%d) | total_guesses=%d (auth=%d anon=%d)',
                $daily->getAttribute('game'),
                $daily->getAttribute('selected_for_date')->toDateString(),
                $solversCount,
                $authSolvers,
                $anonSolvers,
                $totalGuesses,
                $authTotalGuesses,
                $anonTotalGuesses
            ));
        }

        $this->info("Updated daily games: {$updated}");

        return self::SUCCESS;
    }
}
