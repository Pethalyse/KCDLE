<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyGame;
use App\Models\Player;
use App\Models\User;
use App\Models\UserGameResult;
use App\Services\Dle\PlayerComparisonService;
use App\Services\GameGuessService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * API controller responsible for submitting and retrieving daily game guesses.
 *
 * This controller exposes endpoints to:
 * - submit a guess for today's daily game,
 * - retrieve today's guesses for the authenticated user,
 * - retrieve a win history and the detailed guesses for a past date.
 */
class GameGuessController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @param GameGuessService        $guessService Service handling guess submission and persistence.
     * @param PlayerComparisonService $comparison   Service used to compare the guess with the secret player.
     */
    public function __construct(
        protected GameGuessService $guessService,
        protected PlayerComparisonService $comparison
    ) {}

    /**
     * Submit a new guess for today's daily game.
     *
     * @param string  $game    Identifier of the game ('kcdle', 'lfldle', 'lecdle').
     * @param Request $request HTTP request containing guess data.
     *
     * @return JsonResponse
     */
    public function store(string $game, Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id' => ['required', 'integer'],
            'guesses' => ['required', 'integer', 'min:1'],
        ]);

        $result = $this->guessService->submitGuess(
            $game,
            $request,
            (int) $data['player_id'],
            (int) $data['guesses']
        );

        return response()->json($result['payload'], (int) $result['status']);
    }

    /**
     * Retrieve today's game status and guesses for the current user.
     *
     * @param string  $game    Identifier of the game ('kcdle', 'lfldle', 'lecdle').
     * @param Request $request HTTP request with authenticated user.
     *
     * @return JsonResponse
     */
    public function today(string $game, Request $request): JsonResponse
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

        $daily = DailyGame::where('game', $game)
            ->whereDate('selected_for_date', today())
            ->first();

        if (!$daily) {
            return response()->json([
                'message' => 'No daily game configured for today.',
            ], Response::HTTP_NOT_FOUND);
        }

        $result = UserGameResult::with('guesses')
            ->where('user_id', $user->getAttribute('id'))
            ->where('daily_game_id', $daily->getAttribute('id'))
            ->first();

        if (!$result) {
            return response()->json([
                'has_result' => false,
                'won' => false,
                'guesses_count' => 0,
                'guesses' => [],
            ]);
        }

        $secretWrapper = $daily->getAttribute('player_model');

        $entries = [];

        foreach ($result->getRelation('guesses') as $guessRecord) {
            $guessWrapper = Player::resolvePlayerModel($game, (int) $guessRecord->getAttribute('player_id'));

            if (!$secretWrapper || !$guessWrapper) {
                continue;
            }

            $comparison = $this->comparePlayers($secretWrapper, $guessWrapper, $game);
            $correct = (bool) ($comparison['correct'] ?? false);

            $entries[] = [
                'player_id' => (int) $guessRecord->getAttribute('player_id'),
                'correct' => $correct,
                'comparison' => $comparison,
                'stats' => [
                    'solvers_count' => $daily->getAttribute('solvers_count'),
                    'total_guesses' => $daily->getAttribute('total_guesses'),
                    'average_guesses' => $daily->getAttribute('average_guesses'),
                ],
            ];
        }

        return response()->json([
            'has_result' => true,
            'won' => $result->getAttribute('won_at') !== null,
            'guesses_count' => (int) $result->getAttribute('guesses_count'),
            'guesses' => $entries,
        ]);
    }

    /**
     * Retrieve the history of past wins for the authenticated user.
     *
     * @param string  $game    Identifier of the game.
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse
     */
    public function history(string $game, Request $request): JsonResponse
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

        $rows = UserGameResult::query()
            ->where('user_id', $user->getAttribute('id'))
            ->where('game', $game)
            ->whereNotNull('won_at')
            ->join('daily_games', 'user_game_results.daily_game_id', '=', 'daily_games.id')
            ->orderByDesc('daily_games.selected_for_date')
            ->get([
                'user_game_results.id as id',
                'user_game_results.guesses_count',
                'daily_games.id as daily_id',
                'daily_games.selected_for_date as date',
            ]);

        $history = $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'daily_id' => $row->daily_id,
                'date' => Carbon::parse($row->date)->toDateString(),
                'guesses_count' => (int) $row->guesses_count,
            ];
        })->values();

        return response()->json([
            'game' => $game,
            'entries' => $history,
        ]);
    }

    /**
     * Retrieve detailed guesses for a specific past date and game.
     *
     * @param string  $game    Identifier of the game.
     * @param string  $date    Target daily date in YYYY-MM-DD format.
     * @param Request $request HTTP request providing the authenticated user.
     *
     * @return JsonResponse
     */
    public function historyByDate(string $game, string $date, Request $request): JsonResponse
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

        try {
            $targetDate = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (Throwable) {
            return response()->json([
                'message' => 'Invalid date format, expected Y-m-d.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $daily = DailyGame::where('game', $game)
            ->whereDate('selected_for_date', $targetDate)
            ->first();

        if (!$daily) {
            return response()->json([
                'message' => 'No daily game for this date.',
            ], Response::HTTP_NOT_FOUND);
        }

        $result = UserGameResult::with('guesses')
            ->where('user_id', $user->getAttribute('id'))
            ->where('daily_game_id', $daily->getAttribute('id'))
            ->first();

        if (!$result) {
            return response()->json([
                'message' => 'No result for this user and date.',
            ], Response::HTTP_NOT_FOUND);
        }

        $secretWrapper = $daily->getAttribute('player_model');

        $guesses = [];

        foreach ($result->getRelation('guesses') as $guessRecord) {
            $guessWrapper = Player::resolvePlayerModel($game, (int) $guessRecord->getAttribute('player_id'));

            if (!$secretWrapper || !$guessWrapper) {
                continue;
            }

            $comparison = $this->comparePlayers($secretWrapper, $guessWrapper, $game);
            $correct = (bool) ($comparison['correct'] ?? false);

            $guesses[] = [
                'guess_order' => (int) $guessRecord->getAttribute('guess_order'),
                'player_id' => (int) $guessRecord->getAttribute('player_id'),
                'correct' => $correct,
                'comparison' => $comparison,
            ];
        }

        return response()->json([
            'game' => $game,
            'date' => $daily->getAttribute('selected_for_date')->toDateString(),
            'won' => $result->getAttribute('won_at') !== null,
            'guesses_count' => (int) $result->getAttribute('guesses_count'),
            'guesses' => $guesses,
        ]);
    }

    /**
     * Compare the secret player to a guessed player depending on the game.
     *
     * @param mixed  $secret Wrapper model instance representing the secret player.
     * @param mixed  $guess  Wrapper model instance representing the guessed player.
     * @param string $game   Identifier of the game ('kcdle', 'lfldle', 'lecdle').
     *
     * @return array{correct:bool, fields:array} Comparison result.
     */
    protected function comparePlayers(mixed $secret, mixed $guess, string $game): array
    {
        return $this->comparison->comparePlayers($secret, $guess, $game);
    }
}
