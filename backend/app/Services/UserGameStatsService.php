<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserGameResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service responsible for aggregating per-user statistics for daily games.
 *
 * It computes win count, average guesses, current streak and historical max streak
 * for a given user and a given game identifier.
 */
class UserGameStatsService
{
    /**
     * Compute aggregated win statistics for a given user and game.
     *
     * Returned array shape:
     * - 'wins'            => int                 Total number of wins.
     * - 'average_guesses' => float|null          Average guesses per win, rounded to 2 decimals, or null if no data.
     * - 'current_streak'  => int                 Number of consecutive days ending at the most recent win day.
     * - 'max_streak'      => int                 Maximum streak observed over the entire history.
     *
     * @param User   $user User for whom statistics are computed.
     * @param string $game Game identifier (e.g. 'kcdle', 'lfldle', 'lecdle').
     *
     * @return array{wins:int, average_guesses:float|null, current_streak:int, max_streak:int}
     */
    public function getStatsForUserAndGame(User $user, string $game): array
    {
        $rows = UserGameResult::query()
            ->where('user_id', $user->getAttribute('id'))
            ->where('user_game_results.game', $game)
            ->whereNotNull('won_at')
            ->join('daily_games', 'user_game_results.daily_game_id', '=', 'daily_games.id')
            ->orderBy('daily_games.selected_for_date')
            ->get([
                'user_game_results.guesses_count',
                'daily_games.selected_for_date',
            ]);

        if ($rows->isEmpty()) {
            return [
                'wins' => 0,
                'average_guesses' => null,
                'current_streak' => 0,
                'max_streak' => 0,
            ];
        }

        $wins = $rows->count();
        $avg = $rows->avg('guesses_count');
        $avg = $avg !== null ? round($avg, 2) : null;

        $dates = $rows->pluck('selected_for_date')
            ->map(function ($value) {
                return Carbon::parse($value);
            })
            ->values();

        [$currentStreak, $maxStreak] = $this->computeStreaks($dates);

        return [
            'wins' => $wins,
            'average_guesses' => $avg,
            'current_streak' => $currentStreak,
            'max_streak' => $maxStreak,
        ];
    }

    /**
     * Compute the current and maximum win streaks from a list of win dates.
     *
     * The method normalizes entries to calendar days (startOfDay), deduplicates by day,
     * sorts chronologically, then computes:
     * - max streak over the full history
     * - current streak ending at today (if won today) or yesterday (if last win is yesterday)
     *
     * Returned array:
     * - index 0: current streak (int)
     * - index 1: maximum streak (int)
     *
     * @param Collection<int, Carbon|string> $dates Win dates (any precision/order).
     *
     * @return array{0:int, 1:int} [currentStreak, maxStreak]
     */
    protected function computeStreaks(Collection $dates): array
    {
        $days = $dates
            ->map(function ($date) {
                return $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
            })
            ->map(function (Carbon $date) {
                return $date->startOfDay();
            })
            ->unique(function (Carbon $date) {
                return $date->toDateString();
            })
            ->sortBy(function (Carbon $date) {
                return $date->getTimestamp();
            })
            ->values();

        if ($days->isEmpty()) {
            return [0, 0];
        }

        $maxStreak = 0;
        $currentRun = 0;
        $lastDate = null;

        foreach ($days as $date) {
            if ($lastDate === null) {
                $currentRun = 1;
            } elseif ($lastDate->copy()->addDay()->isSameDay($date)) {
                $currentRun++;
            } else {
                $currentRun = 1;
            }

            if ($currentRun > $maxStreak) {
                $maxStreak = $currentRun;
            }

            $lastDate = $date;
        }

        $set = [];
        foreach ($days as $date) {
            $set[$date->toDateString()] = true;
        }

        $today = Carbon::today()->startOfDay();
        $reference = null;

        if (isset($set[$today->toDateString()])) {
            $reference = $today;
        } else {
            $yesterday = $today->copy()->subDay();
            if (isset($set[$yesterday->toDateString()])) {
                $reference = $yesterday;
            }
        }

        if ($reference === null) {
            return [0, $maxStreak];
        }

        $currentStreak = 0;

        while (isset($set[$reference->toDateString()])) {
            $currentStreak++;
            $reference = $reference->copy()->subDay();
        }

        return [$currentStreak, $maxStreak];
    }
}
