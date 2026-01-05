<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserAchievementController extends Controller
{
    /**
     * List achievements unlocked by the authenticated user.
     *
     * This endpoint requires authentication. It resolves the current requester
     * via Request::user() and returns HTTP 401 if the token does not correspond
     * to a valid User instance.
     *
     * For an authenticated user, it retrieves the user's unlocked achievements
     * through the many-to-many relationship, ordered by the pivot column
     * user_achievements.unlocked_at in descending order (most recent unlocks first).
     *
     * Each achievement is normalized into an API-safe structure including the
     * pivot unlock timestamp in ISO 8601 format.
     *
     * Response JSON on success:
     * - 'data' => array<int, array{
     *     id:int,
     *     key:string,
     *     name:string,
     *     description:string,
     *     game:string|null,
     *     unlocked_at:string|null
     * }>
     *
     * Error response:
     * - HTTP 401
     * - { "message": "Unauthenticated." }
     *
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse JSON response containing the unlocked achievements list.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $achievements = $user->achievements()
            ->orderBy('user_achievements.unlocked_at', 'desc')
            ->get()
            ->map(function (Achievement $achievement) {
                return [
                    'id' => $achievement->getAttribute('id'),
                    'key' => $achievement->getAttribute('key'),
                    'name' => $achievement->getAttribute('name'),
                    'description' => $achievement->getAttribute('description'),
                    'game' => $achievement->getAttribute('game'),
                    'unlocked_at' => $achievement->pivot->unlocked_at?->toIso8601String(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $achievements,
        ]);
    }
}
