<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FriendGroup;
use App\Models\User;
use App\Models\UserGameResult;
use App\Services\AchievementService;
use App\Services\Pvp\PvpProfileStatsService;
use App\Services\UserGameStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class UserProfileController extends Controller
{
    protected UserGameStatsService $stats;

    protected AchievementService $achievements;

    protected PvpProfileStatsService $pvp;

    /**
     * @param UserGameStatsService $stats
     * @param AchievementService $achievements
     * @param PvpProfileStatsService $pvp
     */
    public function __construct(
        UserGameStatsService $stats,
        AchievementService $achievements,
        PvpProfileStatsService $pvp
    ) {
        $this->stats = $stats;
        $this->achievements = $achievements;
        $this->pvp = $pvp;
    }

    /**
     * Return the authenticated user's full profile payload.
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

        return response()->json($this->buildProfileResponse($user), Response::HTTP_OK);
    }

    /**
     * Update profile customization (avatar and avatar frame color) and return the same payload as GET /user/profile.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $validated = $request->validate([
            'avatar' => ['nullable', 'file', 'max:5120'],
            'avatar_frame_color' => ['nullable', 'string', 'max:20'],
        ]);

        if ($request->has('avatar_frame_color')) {
            $color = trim((string) ($validated['avatar_frame_color'] ?? ''));
            if ($color === '') {
                $user->setAttribute('avatar_frame_color', '#00a6ff');
            } else {
                if (! preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
                    return response()->json([
                        'message' => 'Invalid avatar_frame_color.',
                        'errors' => [
                            'avatar_frame_color' => ['The avatar_frame_color must be a valid hex color.'],
                        ],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }
                $user->setAttribute('avatar_frame_color', $color);
            }
        }

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            if ($file !== null) {
                $mime = (string) ($file->getMimeType() ?? '');
                $ext = strtolower((string) ($file->getClientOriginalExtension() ?? ''));

                if ($mime === '' || ! str_starts_with($mime, 'image/')) {
                    return response()->json([
                        'message' => 'Invalid avatar file.',
                        'errors' => [
                            'avatar' => ['The avatar must be an image.'],
                        ],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $isGif = $mime === 'image/gif' || $ext === 'gif';
                if ($isGif && ! ((bool) $user->getAttribute('is_admin'))) {
                    return response()->json([
                        'message' => 'GIF avatars are reserved for admins.',
                        'errors' => [
                            'avatar' => ['GIF avatars are reserved for admins.'],
                        ],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $oldPath = (string) ($user->getAttribute('avatar_path') ?? '');
                $stamp = now()->format('YmdHis');

                $filename = $ext !== '' ? "avatar_{$stamp}.{$ext}" : "avatar_{$stamp}";
                $path = $file->storePubliclyAs('users/' . $user->getAttribute('id'), $filename, 'public');

                if ($oldPath !== '' && $oldPath !== 'users/defaut.png' && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }

                $user->setAttribute('avatar_path', $path);
            }
        }

        $user->save();

        $fresh = $user->fresh();
        if (! $fresh instanceof User) {
            return response()->json([
                'message' => 'Unexpected error.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json($this->buildProfileResponse($fresh), Response::HTTP_OK);
    }

    /**
     * Build the full profile response payload used by both GET and POST endpoints.
     *
     * @param User $user
     * @return array<string, mixed>
     */
    protected function buildProfileResponse(User $user): array
    {
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
            ->withCount('users')
            ->get()
            ->map(function (FriendGroup $group) {
                return [
                    'id' => $group->getAttribute('id'),
                    'name' => $group->getAttribute('name'),
                    'slug' => $group->getAttribute('slug'),
                    'join_code' => $group->getAttribute('join_code'),
                    'users_count' => (int) ($group->getAttribute('users_count') ?? 0),
                    'owner' => [
                        'id' => $group->getAttribute('owner')?->getAttribute('id'),
                        'name' => $group->getAttribute('owner')?->getAttribute('name'),
                    ],
                ];
            })
            ->values();

        $pvpStats = $this->pvp->getForUser($user);

        return [
            'user' => [
                'id' => $user->getAttribute('id'),
                'name' => $user->getAttribute('name'),
                'email' => $user->getAttribute('email'),
                'created_at' => $user->getAttribute('created_at')?->toIso8601String(),
                'is_admin' => (bool) $user->getAttribute('is_admin'),
                'avatar_url' => $user->getAttribute('avatar_url'),
                'avatar_frame_color' => (string) ($user->getAttribute('avatar_frame_color') ?? '#00a6ff'),
                'discord_id' => $user->getAttribute('discord_id'),
            ],
            'global_stats' => [
                'total_wins' => $totalWins,
                'global_average_guesses' => $globalAvg !== null ? round($globalAvg, 2) : null,
                'first_win_at' => $firstWinAt,
                'last_win_at' => $lastWinAt,
                'distinct_days_played' => $daysPlayed,
            ],
            'games' => $gamesData,
            'achievements' => [
                'total' => $totalAchievements,
                'unlocked' => $unlockedAchievements,
                'items' => $achievements->values(),
            ],
            'friend_groups' => $friendGroups,
            'pvp' => $pvpStats,
        ];
    }
}
