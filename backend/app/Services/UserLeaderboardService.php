<?php

namespace App\Services;

use App\Models\FriendGroup;
use App\Models\User;
use App\Models\UserGameResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class UserLeaderboardService
{
    /**
     * Get the global leaderboard for a given game.
     *
     * @param string $game
     * @param int $perPage
     * @param int $page
     * @return LengthAwarePaginator
     */
    public function getGlobalLeaderboard(string $game, int $perPage = 50, int $page = 1): LengthAwarePaginator
    {
        $query = UserGameResult::query()
            ->where('user_game_results.game', $game)
            ->whereNotNull('won_at')
            ->join('daily_games', 'user_game_results.daily_game_id', '=', 'daily_games.id');

        return $this->buildLeaderboardFromQuery($query, $perPage, $page);
    }

    /**
     * Get the leaderboard restricted to a friend group for a given game.
     *
     * @param string $game
     * @param FriendGroup $group
     * @param int $perPage
     * @param int $page
     * @return LengthAwarePaginator
     */
    public function getGroupLeaderboard(string $game, FriendGroup $group, int $perPage = 50, int $page = 1): LengthAwarePaginator
    {
        $memberIds = $group->users()->pluck('users.id')->all();

        if (empty($memberIds)) {
            return new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        }

        $query = UserGameResult::query()
            ->where('game', $game)
            ->whereNotNull('won_at')
            ->whereIn('user_game_results.user_id', $memberIds)
            ->join('daily_games', 'user_game_results.daily_game_id', '=', 'daily_games.id');

        return $this->buildLeaderboardFromQuery($query, $perPage, $page);
    }

    /**
     * Build leaderboard from a base query.
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param int $perPage
     * @param int $page
     * @return LengthAwarePaginator
     */
    protected function buildLeaderboardFromQuery($query, int $perPage, int $page): LengthAwarePaginator
    {
        $rows = $query->get([
            'user_game_results.user_id as user_id',
            'user_game_results.guesses_count as guesses_count',
            'daily_games.solvers_count as solvers_count',
            'daily_games.total_guesses as total_guesses',
        ]);


        if ($rows->isEmpty()) {
            return new LengthAwarePaginator(
                collect(),
                0,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );
        }

        $grouped = $rows->groupBy('user_id');

        $userIds = $grouped->keys()->all();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $scored = $grouped->map(function (Collection $entries, int $userId) use ($users) {
            $wins = $entries->count();

            $baseScore = $entries->reduce(function (float $carry, $entry) {
                $guesses = (int) $entry->guesses_count;
                if ($guesses <= 0) {
                    return $carry;
                }

                $solversCount = (int) ($entry->solvers_count ?? 0);
                $totalGuesses = (int) ($entry->total_guesses ?? 0);

                if ($solversCount > 0 && $totalGuesses > 0) {
                    $dayAvg = $totalGuesses / $solversCount;
                } else {
                    $dayAvg = 4.0;
                }

                $dailyScore = $dayAvg / $guesses;

                return $carry + $dailyScore;
            }, 0.0);


            $weight = 1.0 - exp(-$wins / 10.0);

            $finalScore = $baseScore * $weight;

            $averageGuesses = $entries->avg('guesses_count');
            $averageGuesses = $averageGuesses !== null ? (float) $averageGuesses : null;
            if ($averageGuesses !== null) {
                $averageGuesses = round($averageGuesses, 2);
            }

            $user = $users->get($userId);

            return [
                'user_id' => $userId,
                'user' => $user ? [
                    'id' => $user->getAttribute('id'),
                    'name' => $user->getAttribute('name'),
                    'email' => $user->getAttribute('email'),
                ] : null,
                'wins' => $wins,
                'average_guesses' => $averageGuesses,
                'base_score' => $baseScore,
                'weight' => $weight,
                'final_score' => $finalScore,
            ];
        })->values();

        $sorted = $scored->sort(function (array $a, array $b) {
            if ($a['final_score'] === $b['final_score']) {
                if ($a['wins'] === $b['wins']) {
                    if ($a['average_guesses'] === $b['average_guesses']) {
                        return $a['user_id'] <=> $b['user_id'];
                    }

                    if ($a['average_guesses'] === null) {
                        return 1;
                    }

                    if ($b['average_guesses'] === null) {
                        return -1;
                    }

                    return $a['average_guesses'] <=> $b['average_guesses'];
                }

                return $b['wins'] <=> $a['wins'];
            }

            return $b['final_score'] <=> $a['final_score'];
        })->values();

        $total = $sorted->count();
        if ($perPage <= 0) {
            $perPage = 50;
        }
        if ($page <= 0) {
            $page = 1;
        }

        $offset = ($page - 1) * $perPage;
        $items = $sorted->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
