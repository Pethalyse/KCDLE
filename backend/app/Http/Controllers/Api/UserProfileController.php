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
     * @param UserGameStatsService $stats
     * @param AchievementService $achievements
     */
    public function __construct(UserGameStatsService $stats, AchievementService $achievements)
    {
        $this->stats = $stats;
        $this->achievements = $achievements;
    }

    /**
     * Get full loyalty profile for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
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
