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
     * @param UserLeaderboardService $leaderboard
     */
    public function __construct(UserLeaderboardService $leaderboard)
    {
        $this->leaderboard = $leaderboard;
    }

    /**
     * Get the global leaderboard for a given game.
     *
     * @param string $game
     * @param Request $request
     * @return JsonResponse
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
