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
     * @param UserGameStatsService $stats
     */
    public function __construct(UserGameStatsService $stats)
    {
        $this->stats = $stats;
    }

    /**
     * Get aggregated stats for the authenticated user on a given game.
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
