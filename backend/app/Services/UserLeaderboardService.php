<?php

namespace App\Services;

use App\Models\FriendGroup;
use App\Models\User;
use App\Models\UserGameResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Compute global and group leaderboards for the application.
 *
 * This service aggregates daily wins per user and computes ranking metrics
 * such as win count, average guesses and a composite score used for ordering.
 */
class UserLeaderboardService
{
    /**
     * Build the global leaderboard for the given game across all users.
     *
     * The leaderboard is computed from UserGameResult joined with DailyGame:
     * - each row corresponds to one user-day win,
     * - a custom scoring algorithm is applied based on daily average guesses
     *   and the user's guesses for that day,
     * - results are aggregated by user to obtain:
     *   - number of wins,
     *   - average guesses,
     *   - base score,
     *   - weight,
     *   - final composite score used for ordering.
     *
     * The paginator items are associative arrays with (at least):
     * - 'user_id'         => int
     * - 'user'            => array{id:int, name:string, email:string}|null
     * - 'wins'            => int
     * - 'average_guesses' => float|null
     * - 'base_score'      => float
     * - 'weight'          => float
     * - 'final_score'     => float
     *
     * Ordering priority:
     * - final_score (desc),
     * - wins (desc),
     * - average_guesses (asc, nulls last),
     * - user_id (asc).
     *
     * @param string $game    Game identifier (e.g. 'kcdle', 'lfldle', 'lecdle').
     * @param int    $perPage Number of entries per page (defaults to 50, minimum 1).
     * @param int    $page    Page index starting at 1.
     *
     * @return LengthAwarePaginator Paginator of leaderboard entries.
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
     * Build a leaderboard restricted to the members of a friend group.
     *
     * This method works like getGlobalLeaderboard(), but filters the underlying
     * UserGameResult rows to only include users that belong to the given
     * FriendGroup. If the group has no members, an empty paginator is returned.
     *
     * The item structure and ordering semantics are identical to the global leaderboard:
     * - 'user_id', 'user', 'wins', 'average_guesses', 'base_score', 'weight', 'final_score'.
     *
     * @param string      $game    Game identifier (e.g. 'kcdle', 'lfldle', 'lecdle').
     * @param FriendGroup $group   Friend group whose members will be considered.
     * @param int         $perPage Number of entries per page (defaults to 50).
     * @param int         $page    Page index starting at 1.
     *
     * @return LengthAwarePaginator Paginator of leaderboard entries for the group.
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
                    'path'   => request()->url(),
                    'query'  => request()->query(),
                ]
            );
        }

        $query = UserGameResult::query()
            ->where('user_game_results.game', $game)
            ->whereNotNull('won_at')
            ->whereIn('user_game_results.user_id', $memberIds)
            ->join('daily_games', 'user_game_results.daily_game_id', '=', 'daily_games.id');

        return $this->buildLeaderboardFromQuery($query, $perPage, $page);
    }

    /**
     * Transform a base wins query into a scored, paginated leaderboard.
     *
     * The provided query must select UserGameResult wins joined with DailyGame,
     * and will be executed to fetch per-day entries. This method then:
     * - groups entries by user_id,
     * - loads the User models for each user,
     * - computes per-user:
     *   - wins count,
     *   - average guesses,
     *   - base score and weight (according to the scoring algorithm),
     *   - final_score used for ranking,
     * - sorts users according to the ranking rules,
     * - slices the sorted list according to $perPage and $page,
     * - returns a LengthAwarePaginator containing the resulting items.
     *
     * If no rows are returned by the query, an empty paginator is returned.
     *
     * @param Builder $query   Base query builder for user wins joined with daily games.
     * @param int   $perPage Number of entries per page (must be > 0, defaults to 50 if invalid).
     * @param int   $page    Page index starting at 1 (defaults to 1 if invalid).
     *
     * @return LengthAwarePaginator Paginator with scored leaderboard entries.
     */
    protected function buildLeaderboardFromQuery(Builder $query, int $perPage, int $page): LengthAwarePaginator
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
                    'path'  => request()->url(),
                    'query' => request()->query(),
                ]
            );
        }

        $grouped = $rows->groupBy('user_id');

        $userIds = $grouped->keys()->all();

        $users = User::query()
            ->whereIn('id', $userIds)
            ->get([
                'id',
                'name',
                'email',
                'is_admin',
                'avatar_path',
                'avatar_frame_color',
                'discord_id',
                'discord_avatar_hash',
            ])
            ->keyBy('id');

        $scored = $grouped->map(function (Collection $entries, int $userId) use ($users) {
            $wins = $entries->count();

            $baseScore = $entries->reduce(function (float $carry, $entry) {
                $guesses = (int) $entry->guesses_count;
                if ($guesses <= 0) {
                    return $carry;
                }

                $solversCount = $entry->solvers_count ?? 0;
                $totalGuesses = $entry->total_guesses ?? 0;

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
                'user_id'         => $userId,
                'user'            => $user ? [
                    'id'    => $user->getAttribute('id'),
                    'name'  => $user->getAttribute('name'),
                    'email' => $user->getAttribute('email'),
                    'is_admin' => $user->getAttribute('is_admin'),
                    'avatar_url' => (string) $user->getAttribute('avatar_url'),
                    'avatar_frame_color' => (string) $user->getAttribute('avatar_frame_color'),
                ] : null,
                'wins'            => $wins,
                'average_guesses' => $averageGuesses,
                'base_score'      => $baseScore,
                'weight'          => $weight,
                'final_score'     => $finalScore,
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
                'path'  => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
