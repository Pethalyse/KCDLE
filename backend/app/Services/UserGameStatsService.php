<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserGameResult;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class UserGameStatsService
{
    /**
     * Get aggregated stats for a user and a game.
     *
     * @param User $user
     * @param string $game
     * @return array<string, mixed>
     */
    public function getStatsForUserAndGame(User $user, string $game): array
    {
        $rows = UserGameResult::query()
            ->where('user_id', $user->getAttribute('id'))
            ->where('game', $game)
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
                return Carbon::parse($value)->startOfDay();
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
     * Compute current and maximum streak from a sorted list of win dates.
     *
     * @param Collection<int, Carbon> $dates
     * @return array{int, int}
     */
    protected function computeStreaks(Collection $dates): array
    {
        $maxStreak = 0;
        $currentRun = 0;
        $lastDate = null;

        foreach ($dates as $date) {
            if ($lastDate === null) {
                $currentRun = 1;
            } elseif ($date->diffInDays($lastDate) === 1) {
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
        foreach ($dates as $date) {
            $set[$date->toDateString()] = true;
        }

        $today = Carbon::today();
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
