<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyGame;
use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use App\Models\Player;
use App\Services\AchievementService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use Throwable;


class GameGuessController extends Controller
{
    protected AchievementService $achievements;

    /**
     * @param AchievementService $achievements
     */
    public function __construct(AchievementService $achievements)
    {
        $this->achievements = $achievements;
    }

    /**
     * Handle a guess for the given game.
     *
     * @param string $game
     * @param Request $request
     * @return JsonResponse
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
        $correct = $comparison['correct'] ?? false;

        Log::channel('guess')->info('Guess attempt', [
            'ip'        => $request->ip(),
            'game'      => $game,
            'player_id' => $data['player_id'],
            'correct'   => $correct,
            'guesses'   => $data['guesses'],
        ]);

        $user = $request->user();
        if ($user instanceof User) {
            $this->persistUserGuess($user, $daily, (int) $data['player_id'], (int) $data['guesses'], $correct);
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
        ]);
    }

    /**
     * Persist the guess for an authenticated user.
     *
     * @param User $user
     * @param DailyGame $daily
     * @param int $playerId
     * @param int $guessesCount
     * @param bool $correct
     * @return void
     */
    protected function persistUserGuess(User $user, DailyGame $daily, int $playerId, int $guessesCount, bool $correct): void
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

        if ($correct && !$wasWonBefore && $result->getAttribute('won_at') !== null) {
            $this->achievements->handleGameWin($user, $result);
        }
    }


    /**
     * Get today's guesses for the authenticated user and given game.
     *
     * @param string $game
     * @param Request $request
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
     * Get history of wins for the authenticated user and given game.
     *
     * @param string $game
     * @param Request $request
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
     * Get detailed history for a given date, game and authenticated user.
     *
     * @param string $game
     * @param string $date
     * @param Request $request
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

    protected function comparePlayers($secret, $guess, string $game): array
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
     * @param mixed $a
     * @param mixed $b
     * @return int
     *
     * 0 : false
     * 1 : true
     */
    protected function eq(mixed $a, mixed $b): int
    {
        return (int) ($a === $b);
    }

    /**
     * @param float|null $secret
     * @param float|null $guess
     * @return int|null
     *
     * -1 : secret < guess
     *  0 : secret > guess
     *  1 : secret == guess
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
     * @param string|DateTimeInterface|null $secret
     * @param string|DateTimeInterface|null $guess
     * @return int|null
     *
     * Compare l'age selon les dates données.
     * -1 : secret est plus jeune
     *  0 : secret est plus âgé
     *  1 : même date
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
