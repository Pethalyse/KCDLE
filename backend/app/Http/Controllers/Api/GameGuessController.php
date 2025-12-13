<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\DailyGame;
use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use App\Models\PendingGuess;
use App\Models\Player;
use App\Services\AchievementService;
use App\Services\AnonKeyService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use Throwable;


class GameGuessController extends Controller
{
    protected AchievementService $achievements;
    protected AnonKeyService $anonKeys;

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
     * @param AchievementService $achievements Service used to evaluate and unlock
     *                                         achievements when a user wins a game.
     * @param AnonKeyService $anonKeys Service used to create an anonymize key
     *                                 from user ip.
     */
    public function __construct(
        AchievementService $achievements,
        AnonKeyService $anonKeys,
    ) {
        $this->achievements = $achievements;
        $this->anonKeys = $anonKeys;
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
     * - resolves the secret player wrapper model using Player::resolvePlayerModel().
     * - resolves the guessed player wrapper; if either is missing, returns HTTP 404.
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
     * - 'game'                 => string
     * - 'date'                 => string (YYYY-MM-DD)
     * - 'correct'              => bool
     * - 'comparison'           => array  Structured comparison fields.
     * - 'stats'                => array{
     *       solvers_count:int,
     *       total_guesses:int,
     *       average_guesses:float|null
     *   }
     * - 'unlocked_achievements'=> array[] List of newly unlocked achievements (for authenticated users).
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
        $guessWrapper  = Player::resolvePlayerModel($game, $data['player_id']);

        if (!$secretWrapper || !$guessWrapper) {
            return response()->json([
                'message' => 'Invalid player.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $comparison = $this->comparePlayers($secretWrapper, $guessWrapper, $game);
        $correct    = $comparison['correct'] ?? false;

        Log::channel('guess')->info('Guess attempt', [
            'ip'        => $request->ip(),
            'game'      => $game,
            'player_id' => $data['player_id'],
            'correct'   => $correct,
            'guesses'   => $data['guesses'],
        ]);

        $user = null;
        $unlockedAchievements = collect();

        $plainToken = $request->bearerToken();
        if ($plainToken !== null) {
            $accessToken = PersonalAccessToken::findToken($plainToken);
            if ($accessToken !== null && $accessToken->getAttribute("tokenable") instanceof User) {
                $user = $accessToken->getAttribute("tokenable");
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
                'player_id'          => $data['player_id'],
                'total_guesses_used' => $data['guesses'],
                'daily_id'           => $daily->getAttribute('id'),
            ]);

            $daily->increment('solvers_count');
            $daily->increment('total_guesses', $data['guesses']);
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
     * This method:
     * - loads or creates a UserGameResult for the (user, daily_game) pair,
     * - updates the guesses_count if it differs from the provided value,
     * - if the guess is correct and the game was not previously won, sets
     *   won_at to the current time,
     * - saves the UserGameResult,
     * - creates or updates a UserGuess record for the given guessesCount
     *   (guess_order) and player_id,
     * - if the guess is correct and the game transitioned from "not won" to
     *   "won", calls AchievementService::handleGameWin() and returns the
     *   unlocked achievements.
     *
     * If the game was already won before this guess, no additional achievements
     * are unlocked by this method.
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
     * This endpoint:
     * - checks that the game identifier is supported,
     * - loads today's DailyGame for the given game; returns 404 if not found,
     * - loads the UserGameResult and its UserGuess entries for today,
     *
     * The guesses are transformed into a list of entries containing:
     * - resolved player wrapper model,
     * - comparison result (via comparePlayers()),
     * - global stats of the daily game (solvers_count, total_guesses, average_guesses),
     * and a boolean 'correct' flag for each guess.
     *
     * Response JSON payload:
     * - 'game'          => string
     * - 'date'          => string (YYYY-MM-DD)
     * - 'won'           => bool   True if the user has already solved the daily.
     * - 'guesses_count' => int    Number of guesses stored for this user/IP today.
     * - 'guesses'       => array[] Each element includes:
     *      - 'player_id'  => int
     *      - 'correct'    => bool
     *      - 'comparison' => array
     *      - 'stats'      => array{
     *            solvers_count:int,
     *            total_guesses:int,
     *            average_guesses:float|null
     *        }
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
            $guessWrapper = Player::resolvePlayerModel($game, $guessRecord->getAttribute('player_id'));

            if (!$secretWrapper || !$guessWrapper) {
                continue;
            }

            $comparison = $this->comparePlayers($secretWrapper, $guessWrapper, $game);
            $correct = $comparison['correct'] ?? false;

            $entries[] = [
                'player_id' => $guessRecord->getAttribute('player_id'),
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
            'guesses_count' => $result->getAttribute('guesses_count'),
            'guesses' => $entries,
        ]);
    }

    /**
     * Retrieve the history of past wins for the authenticated user.
     *
     * This endpoint:
     * - requires an authenticated user,
     * - validates that the game identifier is supported,
     * - queries UserGameResult joined with DailyGame for rows where:
     *   - user_id matches the current user,
     *   - game matches the requested game,
     *   - won_at is not null (only completed wins),
     * - orders results by DailyGame.selected_for_date in descending order,
     * - maps each row to a simplified structure containing:
     *   - internal result id,
     *   - number of guesses used,
     *   - date of the daily game.
     *
     * Response JSON payload:
     * - 'history' => array<int, array{
     *       id:int,
     *       guesses_count:int,
     *       date:string (YYYY-MM-DD)
     *   }>
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
     * This endpoint:
     * - requires an authenticated user,
     * - validates that the game identifier is supported,
     * - parses the provided date and finds the corresponding DailyGame,
     *   returning 404 if not found,
     * - loads the UserGameResult for the (user, daily_game) pair; if no result
     *   exists, returns an empty guesses list,
     * - resolves the secret player wrapper model for the daily; if missing,
     *   returns an empty guesses list,
     * - iterates through the associated UserGuess records in guess order,
     *   and for each one:
     *   - resolves the guessed player wrapper,
     *   - computes the comparison against the secret player via comparePlayers(),
     *   - determines whether the guess is correct,
     *   - attaches current DailyGame stats.
     *
     * Response JSON payload:
     * - 'game'          => string
     * - 'date'          => string (YYYY-MM-DD)
     * - 'won'           => bool|null  True if result is won, null if no result.
     * - 'guesses_count' => int|null   Number of guesses used, null if no result.
     * - 'guesses'       => array[]    List of guess entries:
     *      - 'player_id'  => int
     *      - 'correct'    => bool
     *      - 'comparison' => array
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
            $guessWrapper = Player::resolvePlayerModel($game, $guessRecord->getAttribute('player_id'));

            if (!$secretWrapper || !$guessWrapper) {
                continue;
            }

            $comparison = $this->comparePlayers($secretWrapper, $guessWrapper, $game);
            $correct = $comparison['correct'] ?? false;

            $guesses[] = [
                'guess_order' => $guessRecord->getAttribute('guess_order'),
                'player_id' => $guessRecord->getAttribute('player_id'),
                'correct' => $correct,
                'comparison' => $comparison,
            ];
        }

        return response()->json([
            'game' => $game,
            'date' => $daily->getAttribute('selected_for_date')->toDateString(),
            'won' => $result->getAttribute('won_at') !== null,
            'guesses_count' => $result->getAttribute('guesses_count'),
            'guesses' => $guesses,
        ]);
    }

    /**
     * Compare the secret player to a guessed player depending on the game.
     *
     * This method dispatches to the appropriate comparison implementation
     * based on the game identifier:
     * - 'kcdle'         => compareKcdlePlayers()
     * - 'lfldle','lecdle'=> compareLoldlePlayers()
     * - any other value => returns a default 'incorrect' comparison.
     *
     * The returned array always has the following structure:
     * - 'correct' => bool  True if the guess matches the secret player.
     * - 'fields'  => array Field-specific comparison values understood by the frontend.
     *
     * @param mixed  $secret Wrapper model instance representing the secret player.
     * @param mixed  $guess  Wrapper model instance representing the guessed player.
     * @param string $game   Identifier of the game ('kcdle', 'lfldle', 'lecdle').
     *
     * @return array{correct:bool, fields:array} Comparison result.
     */
    protected function comparePlayers(mixed $secret, mixed $guess, string $game): array
    {
        return match ($game) {
            'kcdle'  => $this->compareKcdlePlayers($secret, $guess),
            'lfldle', 'lecdle' => $this->compareLoldlePlayers($secret, $guess),
            default  => [
                'correct' => false,
                'fields'  => [],
            ],
        };
    }

    /**
     * Compare two KcdlePlayer instances and compute field-level hints.
     *
     * The comparison covers multiple attributes of the wrapped Player and
     * KcdlePlayer models, including:
     * - slug (exact equality, used to determine global correctness),
     * - country_code (exact equality),
     * - role_id (exact equality),
     * - game_id (exact equality),
     * - currentTeam id (exact equality),
     * - previousTeam id (exact equality),
     * - birthday (age comparison),
     * - first_official_year (numeric comparison),
     * - trophies_count (numeric comparison).
     *
     * For equality-based fields, eq() returns:
     * - 1 if values are equal,
     * - 0 otherwise.
     *
     * For numeric and date based fields, cmpNumber() / cmpDate() return:
     * -  1 if secret == guess,
     * -  0 if secret > guess,
     * - -1 if secret < guess,
     * - null if either side is null.
     *
     * The guess is considered globally correct if the slug comparison returns 1.
     *
     * Returned array structure:
     * - 'correct' => bool  True if slug matches.
     * - 'fields'  => array{
     *       country:int|null,
     *       birthday:int|null,
     *       game:int|null,
     *       first_official_year:int|null,
     *       trophies:int|null,
     *       previous_team:int|null,
     *       current_team:int|null,
     *       role:int|null,
     *       slug:int
     *   }
     *
     * @param KcdlePlayer $secret KcdlePlayer wrapper for the secret player.
     * @param KcdlePlayer $guess  KcdlePlayer wrapper for the guessed player.
     *
     * @return array{correct:bool, fields:array<string,int|null>} Detailed comparison result.
     */
    protected function compareKcdlePlayers(KcdlePlayer $secret, KcdlePlayer $guess): array
    {
        $secretPlayer = $secret->getAttribute('player');
        $guessPlayer  = $guess->getAttribute('player');

        $slug      = $this->eq($secretPlayer?->getAttribute('slug'), $guessPlayer?->getAttribute('slug'));
        $country   = $this->eq($secretPlayer?->getAttribute('country_code'), $guessPlayer?->getAttribute('country_code'));
        $role      = $this->eq($secretPlayer?->getAttribute('role_id'), $guessPlayer?->getAttribute('role_id'));
        $gameField = $this->eq($secret->getAttribute('game_id'), $guess->getAttribute('game_id'));
        $currentTeam = $this->eq(
            $secret->getAttribute('currentTeam')?->getAttribute('id'),
                $guess->getAttribute('currentTeam')?->getAttribute('id')
        );
        $previousTeam = $this->eq(
            $secret->getAttribute('previousTeam')?->getAttribute('id'),
            $guess->getAttribute('previousTeam')?->getAttribute('id')
        );

        $birthday = $this->cmpDate(
            $secretPlayer?->getAttribute('birthdate'),
            $guessPlayer?->getAttribute('birthdate')
        );

        $firstOfficialYear = $this->cmpNumber(
            $secret->getAttribute('first_official_year'),
            $guess->getAttribute('first_official_year')
        );
        $trophies = $this->cmpNumber(
            $secret->getAttribute('trophies_count'),
            $guess->getAttribute('trophies_count')
        );

        $correct = $slug === 1;

        return [
            'correct' => $correct,
            'fields'  => [
                'country'            => $country,
                'birthday'           => $birthday,
                'game'               => $gameField,
                'first_official_year'=> $firstOfficialYear,
                'trophies'           => $trophies,
                'previous_team'      => $previousTeam,
                'current_team'       => $currentTeam,
                'role'               => $role,
                'slug'               => $slug,
            ],
        ];
    }


    /**
     * Compare two LoldlePlayer instances and compute field-level hints.
     *
     * The comparison covers:
     * - slug (exact equality, used to determine global correctness),
     * - country_code (exact equality),
     * - birthday (age comparison),
     * - current team id (exact equality),
     * - lol role id (exact equality).
     *
     * The guess is considered globally correct if the slug comparison returns 1.
     *
     * Returned array structure:
     * - 'correct' => bool  True if slug matches.
     * - 'fields'  => array{
     *       country:int|null,
     *       birthday:int|null,
     *       team:int|null,
     *       lol_role:int|null,
     *       slug:int
     *   }
     *
     * @param LoldlePlayer $secret LoldlePlayer wrapper for the secret player.
     * @param LoldlePlayer $guess  LoldlePlayer wrapper for the guessed player.
     *
     * @return array{correct:bool, fields:array<string,int|null>} Detailed comparison result.
     */
    protected function compareLoldlePlayers(LoldlePlayer $secret, LoldlePlayer $guess): array
    {
        $secretPlayer = $secret->getAttribute('player');
        $guessPlayer  = $guess->getAttribute('player');

        $slug      = $this->eq($secretPlayer?->getAttribute('slug'), $guessPlayer?->getAttribute('slug'));
        $country   = $this->eq($secretPlayer?->getAttribute('country_code'), $guessPlayer?->getAttribute('country_code'));

        $birthday = $this->cmpDate(
            $secretPlayer?->getAttribute('birthdate'),
            $guessPlayer?->getAttribute('birthdate')
        );

        $team = $this->eq(
            $secret->getAttribute('team_id'),
            $guess->getAttribute('team_id')
        );

        $lolRole = $this->eq(
            $secret->getAttribute('lol_role'),
            $guess->getAttribute('lol_role')
        );

        $correct = $slug === 1;

        return [
            'correct' => $correct,
            'fields'  => [
                'country'  => $country,
                'birthday' => $birthday,
                'team'     => $team,
                'lol_role' => $lolRole,
                'slug'     => $slug,
            ],
        ];
    }

    /**
     * Compare two values for strict equality.
     *
     * The comparison uses the strict === operator. The result is encoded
     * as an integer flag to be consumed by the frontend:
     * - 0 => values are not equal,
     * - 1 => values are strictly equal.
     *
     * @param mixed $a Left-hand value.
     * @param mixed $b Right-hand value.
     *
     * @return int 1 if values are strictly equal, 0 otherwise.
     */
    protected function eq(mixed $a, mixed $b): int
    {
        return (int) ($a === $b);
    }

    /**
     * Compare two numeric values and return a directional hint.
     *
     * If either value is null, null is returned to indicate that no hint
     * can be computed.
     *
     * Otherwise:
     * - returns  1 if secret == guess,
     * - returns  0 if secret > guess,
     * - returns -1 if secret < guess.
     *
     * These codes are interpreted by the frontend to indicate whether the
     * guessed value is too low, too high, or correct relative to the secret.
     *
     * @param float|null $secret Secret numeric value.
     * @param float|null $guess  Guessed numeric value.
     *
     * @return int|null Directional comparison result or null when unavailable.
     */
    protected function cmpNumber(?float $secret, ?float $guess): ?int
    {
        if ($secret === null || $guess === null) {
            return null;
        }

        if ($secret === $guess) {
            return 1;
        }

        return $secret < $guess ? -1 : 0;
    }

    /**
     * Compare two dates based on their age in years.
     *
     * The method accepts strings, DateTimeInterface instances or null values.
     * If either date is null, null is returned, meaning no hint can be given.
     *
     * When both are non-null:
     * - both values are converted to Carbon instances,
     * - their age in years is computed,
     * - returns  1 if ages are equal,
     * - returns  0 if secret age > guess age,
     * - returns -1 if secret age < guess age.
     *
     * From the player's viewpoint, this indicates whether the guessed player
     * is older, younger, or the same age as the secret player.
     *
     * @param string|DateTimeInterface|null $secret Secret date or null.
     * @param string|DateTimeInterface|null $guess  Guessed date or null.
     *
     * @return int|null Directional comparison result or null when values are missing.
     */
    protected function cmpDate(null|string|DateTimeInterface $secret, null|string|DateTimeInterface $guess): ?int
    {
        if ($secret === null || $guess === null) {
            return null;
        }

        $s = $secret instanceof Carbon ? $secret->age : Carbon::parse($secret)->age;
        $g = $guess instanceof Carbon ? $guess->age : Carbon::parse($guess)->age;

        if ($s === $g) {
            return 1;
        }

        return $s < $g ? -1 : 0;
    }
}
