<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserGameStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserGameStatsController extends Controller
{
    protected UserGameStatsService $stats;

    /**
     * Create a new UserGameStatsController instance.
     *
     * This controller exposes a read-only endpoint to retrieve aggregated game
     * statistics for the authenticated user. All statistics computation is
     * delegated to the UserGameStatsService.
     *
     * @param UserGameStatsService $stats Service responsible for computing user game statistics.
     */
    public function __construct(UserGameStatsService $stats)
    {
        $this->stats = $stats;
    }

    /**
     * Retrieve aggregated statistics for the authenticated user on a given game.
     *
     * Behavior:
     * - Validates the game identifier against the allowed list: 'kcdle', 'lfldle', 'lecdle'.
     *   If the game is not supported, returns HTTP 404 with:
     *   { "message": "Unknown game." }.
     * - Resolves the requester using Request::user().
     *   If no authenticated User instance is available, returns HTTP 401 with:
     *   { "message": "Unauthenticated." }.
     * - Delegates computation to UserGameStatsService::getStatsForUserAndGame().
     * - Returns a JSON payload containing the game identifier and the computed stats.
     *
     * Response JSON on success:
     * - 'game'  => string
     * - 'stats' => array<string, mixed> Aggregated stats as returned by the service.
     *
     * @param string  $game    Game identifier.
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse JSON response containing the aggregated stats.
     */
    public function show(string $game, Request $request): JsonResponse
    {
        if (!in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            return response()->json([
                'message' => 'Unknown game.',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $request->user();

        if (!$user instanceof User) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $stats = $this->stats->getStatsForUserAndGame($user, $game);

        return response()->json([
            'game' => $game,
            'stats' => $stats,
        ]);
    }
}
