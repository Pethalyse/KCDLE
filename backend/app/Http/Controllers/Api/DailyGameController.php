<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyGameController extends Controller
{
    /**
     * Retrieve today's configured DailyGame for the given game identifier.
     *
     * This endpoint:
     * - validates the game identifier against the allowed list,
     * - queries the DailyGame table for a row matching:
     *     - game = $game
     *     - selected_for_date = today()
     * - returns HTTP 404 if no daily game exists for today,
     * - otherwise returns a JSON payload containing the DailyGame attributes.
     *
     * Response JSON on success includes:
     * - 'id'               => int
     * - 'game'             => string
     * - 'game_label'       => string|null
     * - 'selected_for_date'=> mixed
     * - 'solvers_count'    => int
     * - 'total_guesses'    => int
     * - 'created_at'       => mixed
     * - 'updated_at'       => mixed
     * - 'average_guesses'  => float|null
     *
     * Response JSON on failure:
     * - HTTP 404
     * - { "message": "No daily game configured for today." }
     *
     * @param string $game Game identifier ('kcdle', 'lfldle', 'lecdle').
     *
     * @return JsonResponse JSON response containing today's daily game or a 404 message.
     */
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

    /**
     * Retrieve a history of DailyGame entries for the given game.
     *
     * This endpoint:
     * - validates the game identifier against the allowed list,
     * - reads an optional 'limit' query parameter (default: 30),
     * - queries the DailyGame table for the given game, ordered by
     *   selected_for_date descending, limited to the requested amount,
     * - returns the list as an array of DailyGame::toArray() payloads.
     *
     * Query parameters:
     * - limit: int (optional) Maximum number of entries to return (default 30).
     *
     * Response JSON:
     * - 'history' => array<int, array<string, mixed>> List of daily games.
     *
     * @param string  $game    Game identifier ('kcdle', 'lfldle', 'lecdle').
     * @param Request $request HTTP request used to read query parameters.
     *
     * @return JsonResponse JSON response containing daily game history.
     */
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

    /**
     * Validate that the provided game identifier is supported.
     *
     * Allowed values are strictly:
     * - 'kcdle'
     * - 'lfldle'
     * - 'lecdle'
     *
     * If the value is not allowed, this method aborts the request with HTTP 404
     * and message "Unknown game."
     *
     * @param string $game Game identifier to validate.
     *
     * @return void
     */
    protected function validateGame(string $game): void
    {
        if (! in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            abort(404, 'Unknown game.');
        }
    }
}
