<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserLeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LeaderboardController extends Controller
{
    protected UserLeaderboardService $leaderboard;

    /**
     * Create a new LeaderboardController instance.
     *
     * This controller exposes a read-only endpoint to retrieve the global leaderboard
     * for a given game. The actual aggregation and scoring logic is delegated to
     * the UserLeaderboardService.
     *
     * @param UserLeaderboardService $leaderboard Service used to compute the global leaderboard.
     */
    public function __construct(UserLeaderboardService $leaderboard)
    {
        $this->leaderboard = $leaderboard;
    }

    /**
     * Retrieve the global leaderboard for a supported game.
     *
     * Behavior:
     * - Validates the game identifier against the allowed list: 'kcdle', 'lfldle', 'lecdle'.
     *   If the game is not supported, returns HTTP 404 with:
     *   { "message": "Unknown game." }.
     * - Reads pagination query parameters:
     *   - per_page (default: 50) cast to int; if <= 0, falls back to 50.
     *   - page (default: 1) cast to int; if <= 0, falls back to 1.
     * - Delegates leaderboard computation to UserLeaderboardService::getGlobalLeaderboard().
     * - Builds the response 'data' array from paginator items while adding a computed
     *   'rank' field based on the current page and per-page size.
     * - Returns pagination metadata from the paginator.
     *
     * Response JSON on success:
     * - 'game' => string
     * - 'data' => array<int, array{
     *     rank:int,
     *     user:mixed,
     *     wins:mixed,
     *     average_guesses:mixed,
     *     base_score:mixed,
     *     weight:mixed,
     *     final_score:mixed
     * }>
     * - 'meta' => array{
     *     current_page:int,
     *     last_page:int,
     *     per_page:int,
     *     total:int
     * }
     *
     * @param string  $game    Game identifier.
     * @param Request $request HTTP request used to read 'per_page' and 'page' query parameters.
     *
     * @return JsonResponse JSON response containing the leaderboard data and pagination meta.
     */
    public function show(string $game, Request $request): JsonResponse
    {
        if (!in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            return response()->json([
                'message' => 'Unknown game.',
            ], Response::HTTP_NOT_FOUND);
        }

        $perPage = (int) $request->query('per_page', 50);
        if ($perPage <= 0) {
            $perPage = 50;
        }

        $page = (int) $request->query('page', 1);
        if ($page <= 0) {
            $page = 1;
        }

        $paginator = $this->leaderboard->getGlobalLeaderboard($game, $perPage, $page);

        $data = [];
        $rankOffset = ($paginator->currentPage() - 1) * $paginator->perPage();
        $index = 0;

        foreach ($paginator->items() as $row) {
            $data[] = [
                'rank' => $rankOffset + (++$index),
                'user' => $row['user'],
                'wins' => $row['wins'],
                'average_guesses' => $row['average_guesses'],
                'base_score' => $row['base_score'],
                'weight' => $row['weight'],
                'final_score' => $row['final_score'],
            ];
        }

        return response()->json([
            'game' => $game,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
