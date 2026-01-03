<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\DailyGame;
use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use App\Models\PendingGuess;
use App\Models\Player;
use App\Models\User;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use App\Services\AchievementService;
use App\Services\AnonKeyService;
use App\Services\Dle\PlayerComparisonService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GameGuessController extends Controller
{
    protected AchievementService $achievements;
    protected AnonKeyService $anonKeys;
    protected PlayerComparisonService $comparison;

    /**
     * Create a new GameGuessController instance.
     *
     * This controller handles the entire lifecycle of guesses for the
     * different games (kcdle, lfldle, lecdle):
     * - recording guesses for authenticated and anonymous users,
     * - computing and returning today's status for the caller,
     * - providing history and per-date details for past games,
     * - comparing guesses to the secret player and building structured
     *   comparison results for the frontend.
     *
     * @param AchievementService      $achievements Service used to evaluate and unlock
     *                                              achievements when a user wins a game.
     * @param AnonKeyService          $anonKeys     Service used to create an anonymize key
     *                                              from user ip.
     * @param PlayerComparisonService $comparison   Centralized DLE-style comparison service.
     */
    public function __construct(
        AchievementService $achievements,
        AnonKeyService $anonKeys,
        PlayerComparisonService $comparison,
    ) {
        $this->achievements = $achievements;
        $this->anonKeys = $anonKeys;
        $this->comparison = $comparison;
    }

    /**
     * Submit a new guess for today's daily game.
     *
     * Supported games: 'kcdle', 'lfldle', 'lecdle'. Any other value results
     * in a 404 JSON error: { "message": "Unknown game." }.
     *
     * This endpoint:
     * - validates the input payload:
     *   - player_id: required, integer.
     *   - guesses:  required, integer, min 1 (position of this guess in the sequence).
     * - loads today's DailyGame for the requested game; if none exists,
     *   returns HTTP 404.
     * - resolves the secret player wrapper model using DailyGame.player_model.
     * - resolves the guessed player wrapper; if either is missing, returns HTTP 422.
     * - uses comparePlayers() to derive a comparison array and detect if
     *   the guess is correct.
     *
     * If the user is authenticated:
     * - calls persistUserGuess() to update UserGameResult and UserGuess,
     *   and possibly unlock achievements.
     *
     * If the user is anonymous:
     * - uses an IP-based anonymous key, and stores or updates a PendingGuess
     *   row keyed by (anon_key, daily_game_id, guess_order).
     *
     * In both cases, the DailyGame aggregate statistics are updated:
     * - solvers_count is incremented when the daily is newly solved,
     * - total_guesses is incremented,
     * - average_guesses is recomputed.
     *
     * Response JSON payload:
     * - 'correct'              => bool
     * - 'comparison'           => array
     * - 'stats'                => array{
     *       solvers_count:int,
     *       total_guesses:int,
     *       average_guesses:float|null
     *   }
     * - 'unlocked_achievements'=> array[] (authenticated only)
     *
     * @param string  $game    Identifier of the game ('kcdle', 'lfldle', 'lecdle').
     * @param Request $request HTTP request containing guess data and authenticated user if any.
     *
     * @return JsonResponse JSON response describing the guess result and stats.
     */
    public function store(string $game, Request $request): JsonResponse
    {
        if (!in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            return response()->json([
                'message' => 'Unknown game.',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = $request->validate([
            'player_id' => ['required', 'integer'],
            'guesses'   => ['required', 'integer', 'min:1'],
        ]);

        $daily = DailyGame::where('game', $game)
            ->whereDate('selected_for_date', today())
            ->first();

        if (!$daily) {
            return response()->json([
                'message' => 'No daily game configured for today.',
            ], Response::HTTP_NOT_FOUND);
        }

        $secretWrapper = $daily->getAttribute('player_model');
        $guessWrapper  = Player::resolvePlayerModel($game, (int) $data['player_id']);

        if (!$secretWrapper || !$guessWrapper) {
            return response()->json([
                'message' => 'Invalid player.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $comparison = $this->comparePlayers($secretWrapper, $guessWrapper, $game);
        $correct    = (bool) ($comparison['correct'] ?? false);

        Log::channel('guess')->info('Guess attempt', [
            'ip'        => $request->ip(),
            'game'      => $game,
            'player_id' => (int) $data['player_id'],
            'correct'   => $correct,
            'guesses'   => (int) $data['guesses'],
        ]);

        $user = null;
        $unlockedAchievements = collect();

        $plainToken = $request->bearerToken();
        if ($plainToken !== null) {
            $accessToken = PersonalAccessToken::findToken($plainToken);
            if ($accessToken !== null && $accessToken->getAttribute('tokenable') instanceof User) {
                $user = $accessToken->getAttribute('tokenable');
            }
        }

        if ($user instanceof User) {
            $unlockedAchievements = $this->persistUserGuess(
                $user,
                $daily,
                (int) $data['player_id'],
                (int) $data['guesses'],
                $correct
            );
        } else {
            $anonKey = $this->anonKeys->fromRequest($request);
            PendingGuess::updateOrCreate(
                [
                    'anon_key'      => $anonKey,
                    'daily_game_id' => $daily->getAttribute('id'),
                    'guess_order'   => (int) $data['guesses'],
                ],
                [
                    'game'      => $game,
                    'player_id' => (int) $data['player_id'],
                    'correct'   => $correct,
                ]
            );
        }

        if ($correct) {
            Log::channel('guess')->info('Correct guess', [
                'ip'                 => $request->ip(),
                'game'               => $game,
                'player_id'          => (int) $data['player_id'],
                'total_guesses_used' => (int) $data['guesses'],
                'daily_id'           => $daily->getAttribute('id'),
            ]);

            $daily->increment('solvers_count');
            $daily->increment('total_guesses', (int) $data['guesses']);
        }

        return response()->json([
            'correct'    => $correct,
            'comparison' => $comparison,
            'stats'      => [
                'solvers_count'   => $daily->getAttribute('solvers_count'),
                'total_guesses'   => $daily->getAttribute('total_guesses'),
                'average_guesses' => $daily->getAttribute('average_guesses'),
            ],
            'unlocked_achievements' => $unlockedAchievements,
        ]);
    }

    /**
     * Persist a guess for an authenticated user and update related stats.
     *
     * @param User      $user         Authenticated user submitting the guess.
     * @param DailyGame $daily        Daily game for which the guess is submitted.
     * @param int       $playerId     Identifier of the guessed player.
     * @param int       $guessesCount Position of this guess within today's guesses.
     * @param bool      $correct      Whether the guess matches the secret player.
     *
     * @return Collection<int, Achievement> Collection of achievements unlocked by this guess.
     */
    protected function persistUserGuess(User $user, DailyGame $daily, int $playerId, int $guessesCount, bool $correct): Collection
    {
        $result = UserGameResult::firstOrCreate(
            [
                'user_id'       => $user->getAttribute('id'),
                'daily_game_id' => $daily->getAttribute('id'),
            ],
            [
                'game'          => $daily->getAttribute('game'),
                'guesses_count' => 0,
            ]
        );

        $wasWonBefore = $result->getAttribute('won_at') !== null;

        if ($result->getAttribute('guesses_count') !== $guessesCount) {
            $result->setAttribute('guesses_count', $guessesCount);
        }

        if ($correct && !$wasWonBefore && $result->getAttribute('won_at') === null) {
            $result->setAttribute('won_at', now());
        }

        $result->save();

        UserGuess::updateOrCreate(
            [
                'user_game_result_id' => $result->getAttribute('id'),
                'guess_order'         => $guessesCount,
            ],
            [
                'player_id'           => $playerId,
            ]
        );

        $unlocked = collect();

        if ($correct && !$wasWonBefore && $result->getAttribute('won_at') !== null) {
            $unlocked = $this->achievements->handleGameWin($user, $result);
        }

        return $unlocked;
    }

    /**
     * Retrieve today's game status and guesses for the current user.
     *
     * @param string  $game    Identifier of the game ('kcdle', 'lfldle', 'lecdle').
     * @param Request $request HTTP request with authenticated user.
     *
     * @return JsonResponse JSON response with today's game status and guesses.
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
     * @return JsonResponse JSON response with the list of completed daily results.
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
     * @return JsonResponse JSON response with detailed guesses for the given date.
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

    /**
     * Backward-compatible wrapper for tests/legacy code.
     *
     * @param KcdlePlayer $secret KcdlePlayer wrapper for the secret player.
     * @param KcdlePlayer $guess  KcdlePlayer wrapper for the guessed player.
     *
     * @return array{correct:bool, fields:array<string,int|null>}
     */
    protected function compareKcdlePlayers(KcdlePlayer $secret, KcdlePlayer $guess): array
    {
        return $this->comparison->compareKcdlePlayers($secret, $guess);
    }

    /**
     * Backward-compatible wrapper for tests/legacy code.
     *
     * @param LoldlePlayer $secret LoldlePlayer wrapper for the secret player.
     * @param LoldlePlayer $guess  LoldlePlayer wrapper for the guessed player.
     *
     * @return array{correct:bool, fields:array<string,int|null>}
     */
    protected function compareLoldlePlayers(LoldlePlayer $secret, LoldlePlayer $guess): array
    {
        return $this->comparison->compareLoldlePlayers($secret, $guess);
    }

    /**
     * Backward-compatible wrapper for tests/legacy code.
     *
     * @param mixed $a Left-hand value.
     * @param mixed $b Right-hand value.
     *
     * @return int 1 if values are strictly equal, 0 otherwise.
     */
    protected function eq(mixed $a, mixed $b): int
    {
        return $this->comparison->eq($a, $b);
    }

    /**
     * Backward-compatible wrapper for tests/legacy code.
     *
     * @param float|null $secret Secret numeric value.
     * @param float|null $guess  Guessed numeric value.
     *
     * @return int|null Directional comparison result or null when unavailable.
     */
    protected function cmpNumber(?float $secret, ?float $guess): ?int
    {
        return $this->comparison->cmpNumber($secret, $guess);
    }

    /**
     * Backward-compatible wrapper for tests/legacy code.
     *
     * @param string|DateTimeInterface|null $secret Secret date or null.
     * @param string|DateTimeInterface|null $guess  Guessed date or null.
     *
     * @return int|null Directional comparison result or null when values are missing.
     */
    protected function cmpDate(null|string|DateTimeInterface $secret, null|string|DateTimeInterface $guess): ?int
    {
        return $this->comparison->cmpDate($secret, $guess);
    }
}
