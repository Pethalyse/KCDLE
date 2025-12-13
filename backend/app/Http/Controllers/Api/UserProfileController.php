<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FriendGroup;
use App\Models\User;
use App\Models\UserGameResult;
use App\Services\AchievementService;
use App\Services\UserGameStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserProfileController extends Controller
{
    protected UserGameStatsService $stats;
    protected AchievementService $achievements;

    /**
     * Create a new UserProfileController instance.
     *
     * This controller exposes a single endpoint returning a consolidated profile
     * for the authenticated user, including:
     * - global aggregated stats across all games,
     * - per-game aggregated stats,
     * - achievement counts,
     * - friend groups membership overview.
     *
     * All per-game statistics are delegated to UserGameStatsService, and the
     * achievements catalog/unlock status is delegated to AchievementService.
     *
     * @param UserGameStatsService $stats Service responsible for computing per-game user stats.
     * @param AchievementService   $achievements Service responsible for listing achievements and unlock status.
     */
    public function __construct(UserGameStatsService $stats, AchievementService $achievements)
    {
        $this->stats = $stats;
        $this->achievements = $achievements;
    }

    /**
     * Retrieve the authenticated user's consolidated profile.
     *
     * This endpoint requires authentication. If Request::user() does not resolve
     * to a User instance, it returns HTTP 401 with { "message": "Unauthenticated." }.
     *
     * The response includes:
     * - user identity fields (id, name, email, created_at),
     * - global win statistics across all games based on UserGameResult rows where won_at is not null:
     *   - total_wins
     *   - global_average_guesses (rounded to 2 decimals, null if no wins)
     *   - first_win_at (min won_at, null if no wins)
     *   - last_win_at (max won_at, null if no wins)
     *   - distinct_days_played (count of distinct daily_games.selected_for_date where the user has a win)
     * - per-game statistics for the hardcoded games list ['kcdle', 'lfldle', 'lecdle']
     *   computed via UserGameStatsService::getStatsForUserAndGame()
     * - achievements summary computed from AchievementService::listAllForUser():
     *   - total achievements
     *   - unlocked achievements (where unlocked === true)
     * - friend groups overview for groups the user belongs to, including owner identity.
     *
     * Response JSON:
     * - 'user' => array{
     *     id:int,
     *     name:string,
     *     email:string|null,
     *     created_at:string|null
     *   }
     * - 'global_stats' => array{
     *     total_wins:int,
     *     global_average_guesses:float|null,
     *     first_win_at:mixed,
     *     last_win_at:mixed,
     *     distinct_days_played:int
     *   }
     * - 'games' => array<string, array<string, mixed>>
     * - 'achievements' => array{ total:int, unlocked:int }
     * - 'friend_groups' => array<int, array{
     *     id:int,
     *     name:string,
     *     slug:string,
     *     join_code:string,
     *     owner:array{id:int|null, name:string|null}
     *   }>
     *
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse JSON response containing the user profile payload.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $games = ['kcdle', 'lfldle', 'lecdle'];

        $gamesData = [];
        foreach ($games as $game) {
            $gamesData[$game] = $this->stats->getStatsForUserAndGame($user, $game);
        }

        $globalQuery = UserGameResult::query()
            ->where('user_id', $user->getAttribute('id'))
            ->whereNotNull('won_at');

        $totalWins = $globalQuery->count();
        $globalAvg = $totalWins > 0 ? (float) $globalQuery->avg('guesses_count') : null;

        $firstWinAt = $totalWins > 0 ? $globalQuery->min('won_at') : null;
        $lastWinAt = $totalWins > 0 ? $globalQuery->max('won_at') : null;

        $daysPlayed = 0;
        if ($totalWins > 0) {
            $daysPlayed = (int) UserGameResult::query()
                ->join('daily_games', 'user_game_results.daily_game_id', '=', 'daily_games.id')
                ->where('user_game_results.user_id', $user->getAttribute('id'))
                ->whereNotNull('user_game_results.won_at')
                ->distinct()
                ->count('daily_games.selected_for_date');
        }

        $achievements = $this->achievements->listAllForUser($user);
        $totalAchievements = $achievements->count();
        $unlockedAchievements = $achievements->where('unlocked', true)->count();

        $friendGroups = $user->friendGroups()
            ->with('owner:id,name')
            ->get()
            ->map(function (FriendGroup $group) {
                return [
                    'id' => $group->getAttribute('id'),
                    'name' => $group->getAttribute('name'),
                    'slug' => $group->getAttribute('slug'),
                    'join_code' => $group->getAttribute('join_code'),
                    'owner' => [
                        'id' => $group->getAttribute("owner")?->getAttribute('id'),
                        'name' => $group->getAttribute("owner")?->getAttribute('name'),
                    ],
                ];
            })
            ->values();

        return response()->json([
            'user' => [
                'id' => $user->getAttribute('id'),
                'name' => $user->getAttribute('name'),
                'email' => $user->getAttribute('email'),
                'created_at' => $user->getAttribute('created_at')?->toIso8601String(),
            ],
            'global_stats' => [
                'total_wins' => $totalWins,
                'global_average_guesses' => $globalAvg !== null ? round($globalAvg, 2) : null,
                'first_win_at' => $firstWinAt ?: null,
                'last_win_at' => $lastWinAt ?: null,
                'distinct_days_played' => $daysPlayed,
            ],
            'games' => $gamesData,
            'achievements' => [
                'total' => $totalAchievements,
                'unlocked' => $unlockedAchievements,
            ],
            'friend_groups' => $friendGroups,
        ]);
    }
}
