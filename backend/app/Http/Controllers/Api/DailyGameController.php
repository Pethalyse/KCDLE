<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyGameController extends Controller
{
    public function show(string $game): JsonResponse
    {
        $this->validateGame($game);

        /**
         * @var DailyGame $daily
         */
        $daily = DailyGame::where('game', $game)
            ->whereDate('selected_for_date', today())
            ->first();

        if (! $daily) {
            return response()->json([
                'message' => 'No daily game configured for today.',
            ], 404);
        }

        return new JsonResponse([
            'id' => $daily->getAttribute('id'),
            'game' => $daily->getAttribute('game'),
            'game_label' => $daily->getAttribute('game_label'),
            'selected_for_date' => $daily->getAttribute('selected_for_date'),
            'solvers_count' => $daily->getAttribute('solvers_count'),
            'total_guesses' => $daily->getAttribute('total_guesses'),
            'created_at' => $daily->getAttribute('created_at'),
            'updated_at' => $daily->getAttribute('updated_at'),
            'average_guesses' => $daily->getAttribute('average_guesses'),
        ]);
    }

    public function history(string $game, Request $request): JsonResponse
    {
        $this->validateGame($game);

        $limit = (int) $request->query('limit', 30);

        $dailies = DailyGame::where('game', $game)
            ->orderByDesc('selected_for_date')
            ->limit($limit)
            ->get();

        return response()->json([
            'history' => $dailies->map(fn (DailyGame $daily) => $daily->toArray()),
        ]);
    }

    protected function validateGame(string $game): void
    {
        if (! in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            abort(404, 'Unknown game.');
        }
    }
}
