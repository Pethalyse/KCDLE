<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\DailyGame;
use App\Models\PendingGuess;
use App\Models\Player;
use App\Models\User;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use App\Services\Dle\PlayerComparisonService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Central service to submit a daily DLE guess (kcdle / lfldle / lecdle).
 *
 * This service is the single source of truth used by:
 * - the public API endpoint (frontend)
 * - the Discord bot API endpoint
 *
 * It handles:
 * - validating the game exists for today
 * - resolving secret/guess wrapper models
 * - building the comparison payload
 * - persisting guesses (authenticated users or anonymous key)
 * - unlocking achievements (authenticated)
 * - updating daily statistics when solved
 * - optionally preventing replay after a win (Discord bot use-case)
 */
class GameGuessService
{
    public function __construct(
        protected AchievementService $achievements,
        protected AnonKeyService $anonKeys,
        protected PlayerComparisonService $comparison
    ) {}

    /**
     * Submit a guess for today's daily game.
     *
     * @param string      $game                 Game identifier ('kcdle', 'lfldle', 'lecdle').
     * @param Request     $request              Incoming request (used for bearer token resolution and logging).
     * @param int         $playerId             Guessed player ID.
     * @param int         $guessOrder           Guess order (1..n).
     * @param User|null   $forcedUser           If provided, this user is used as the authenticated identity.
     * @param string|null $forcedAnonKey        If provided (and no user), this anonymous key is used instead of IP-based key.
     * @param bool        $preventReplayAfterWin If true, rejects guesses once the user/anon has already won today.
     *
     * @return array{status:int,payload:array<string,mixed>}
     */
    public function submitGuess(
        string $game,
        Request $request,
        int $playerId,
        int $guessOrder,
        ?User $forcedUser = null,
        ?string $forcedAnonKey = null,
        bool $preventReplayAfterWin = false
    ): array {
        if (!in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            return [
                'status' => Response::HTTP_NOT_FOUND,
                'payload' => ['message' => 'Unknown game.'],
            ];
        }

        $daily = DailyGame::where('game', $game)
            ->whereDate('selected_for_date', today())
            ->first();

        if (!$daily) {
            return [
                'status' => Response::HTTP_NOT_FOUND,
                'payload' => ['message' => 'No daily game configured for today.'],
            ];
        }

        $secretWrapper = $daily->getAttribute('player_model');
        $guessWrapper = Player::resolvePlayerModel($game, $playerId);

        if (!$secretWrapper || !$guessWrapper) {
            return [
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'payload' => ['message' => 'Invalid player.'],
            ];
        }

        $comparison = $this->comparison->comparePlayers($secretWrapper, $guessWrapper, $game);
        $correct = (bool) ($comparison['correct'] ?? false);

        $user = $forcedUser instanceof User ? $forcedUser : $this->resolveUserFromBearer($request);

        $anonKey = null;
        if (!$user instanceof User) {
            $anonKey = $forcedAnonKey !== null ? $forcedAnonKey : $this->anonKeys->fromRequest($request);
        }

        if ($preventReplayAfterWin) {
            if ($user instanceof User) {
                $existing = UserGameResult::query()
                    ->where('user_id', $user->getAttribute('id'))
                    ->where('daily_game_id', $daily->getAttribute('id'))
                    ->first();

                if ($existing && $existing->getAttribute('won_at') !== null) {
                    return [
                        'status' => Response::HTTP_CONFLICT,
                        'payload' => ['message' => 'Already solved for today.'],
                    ];
                }
            } elseif (is_string($anonKey)) {
                $alreadyWon = PendingGuess::query()
                    ->where('anon_key', $anonKey)
                    ->where('daily_game_id', $daily->getAttribute('id'))
                    ->where('correct', true)
                    ->exists();

                if ($alreadyWon) {
                    return [
                        'status' => Response::HTTP_CONFLICT,
                        'payload' => ['message' => 'Already solved for today.'],
                    ];
                }
            }
        }

        Log::channel('guess')->info('Guess attempt', [
            'ip' => $request->ip(),
            'game' => $game,
            'player_id' => $playerId,
            'correct' => $correct,
            'guesses' => $guessOrder,
            'user_id' => $user instanceof User ? $user->getAttribute('id') : null,
            'anon_key' => $anonKey,
        ]);

        $unlockedAchievements = collect();

        if ($user instanceof User) {
            $unlockedAchievements = $this->persistUserGuess(
                $user,
                $daily,
                $playerId,
                $guessOrder,
                $correct
            );
        } else {
            PendingGuess::updateOrCreate(
                [
                    'anon_key' => (string) $anonKey,
                    'daily_game_id' => $daily->getAttribute('id'),
                    'guess_order' => $guessOrder,
                ],
                [
                    'game' => $game,
                    'player_id' => $playerId,
                    'correct' => $correct,
                ]
            );
        }

        if ($correct) {
            Log::channel('guess')->info('Correct guess', [
                'ip' => $request->ip(),
                'game' => $game,
                'player_id' => $playerId,
                'total_guesses_used' => $guessOrder,
                'daily_id' => $daily->getAttribute('id'),
                'user_id' => $user instanceof User ? $user->getAttribute('id') : null,
                'anon_key' => $anonKey,
            ]);

            $daily->increment('solvers_count');
            $daily->increment('total_guesses', $guessOrder);
        }

        return [
            'status' => Response::HTTP_OK,
            'payload' => [
                'correct' => $correct,
                'comparison' => $comparison,
                'stats' => [
                    'solvers_count' => $daily->getAttribute('solvers_count'),
                    'total_guesses' => $daily->getAttribute('total_guesses'),
                    'average_guesses' => $daily->getAttribute('average_guesses'),
                ],
                'unlocked_achievements' => $unlockedAchievements,
            ],
        ];
    }

    /**
     * Resolve an authenticated user from a bearer token (Sanctum).
     *
     * @param Request $request Request to inspect.
     *
     * @return User|null
     */
    protected function resolveUserFromBearer(Request $request): ?User
    {
        $plainToken = $request->bearerToken();
        if ($plainToken === null) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($plainToken);
        if ($accessToken === null) {
            return null;
        }

        $tokenable = $accessToken->getAttribute('tokenable');

        return $tokenable instanceof User ? $tokenable : null;
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
                'user_id' => $user->getAttribute('id'),
                'daily_game_id' => $daily->getAttribute('id'),
            ],
            [
                'game' => $daily->getAttribute('game'),
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
                'guess_order' => $guessesCount,
            ],
            [
                'player_id' => $playerId,
            ]
        );

        $unlocked = collect();

        if ($correct && !$wasWonBefore && $result->getAttribute('won_at') !== null) {
            $unlocked = $this->achievements->handleGameWin($user, $result);
        }

        return $unlocked;
    }
}
