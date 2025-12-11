<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailyGame;
use App\Models\PendingGuess;
use App\Models\User;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use App\Services\AchievementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        protected AchievementService $achievements,
    ) {}
    /**
     * Inscription + émission d'un token Sanctum.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:20'],
            'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::query()->create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('kcdle-app')->plainTextToken;
        $unlocked = $this->importPendingGuesses($user, $request);

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
            'unlocked_achievements' => $unlocked->values(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Connexion email / mot de passe + nouveau token Sanctum.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->getAttribute('password'))) {
            return response()->json([
                'message' => 'Identifiants invalides.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->tokens()->delete();

        $token = $user->createToken('kcdle-app')->plainTextToken;

        $unlocked = $this->importPendingGuesses($user, $request);

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
            'unlocked_achievements' => $unlocked->values(),
        ]);
    }

    /**
     * Déconnexion : on invalide le token actuel.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Récupère l'utilisateur courant à partir du token.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user ? $this->formatUser($user) : null,
        ]);
    }

    protected function formatUser(User $user): array
    {
        return [
            'id'    => $user->getAttribute("id"),
            'name'  => $user->getAttribute("name"),
            'email' => $user->getAttribute("email"),
        ];
    }

    /**
     * Rattache les pending_guesses à l'utilisateur
     * et déclenche les achievements liés aux win.
     *
     * @param User $user
     * @param Request $request
     * @return Collection
     */
    protected function importPendingGuesses(User $user, Request $request): Collection
    {
        $anonKey = $this->makeAnonKey($request);

        $pending = PendingGuess::query()
            ->where('anon_key', $anonKey)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            return collect();
        }

        $unlocked = collect();

        $byDaily = $pending->groupBy('daily_game_id');

        foreach ($byDaily as $dailyId => $entries) {
            /** @var DailyGame|null $daily */
            $daily = DailyGame::find($dailyId);
            if (!$daily) {
                continue;
            }

            /** @var UserGameResult $result */
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

            if ($result->getAttribute('won_at') !== null) {
                continue;
            }

            $existingGuesses = UserGuess::query()
                ->where('user_game_result_id', $result->getAttribute('id'))
                ->orderBy('guess_order')
                ->get();

            $sequence = [];
            $seen = [];

            foreach ($existingGuesses as $guess) {
                $sequence[] = [
                    'player_id'  => $guess->getAttribute('player_id'),
                    'correct'    => false,
                    'created_at' => $guess->getAttribute('created_at'),
                ];
                $seen[$guess->getAttribute('player_id')] = true;
            }

            $firstCorrectIndex = null;
            $firstCorrectDate  = null;

            /** @var PendingGuess $entry */
            foreach ($entries->sortBy(['created_at', 'guess_order']) as $entry) {
                $playerId = $entry->getAttribute('player_id');

                if (isset($seen[$playerId])) {
                    continue;
                }

                $seen[$playerId] = true;

                $sequence[] = [
                    'player_id'  => $playerId,
                    'correct'    => (bool) $entry->getAttribute('correct'),
                    'created_at' => $entry->getAttribute('created_at'),
                ];

                if ($entry->getAttribute('correct') && $firstCorrectIndex === null) {
                    $firstCorrectIndex = count($sequence); // 1-based
                    $firstCorrectDate  = $entry->getAttribute('created_at');
                }
            }

            if (empty($sequence)) {
                continue;
            }

            foreach ($sequence as $index => $item) {
                $order = $index + 1;

                UserGuess::updateOrCreate(
                    [
                        'user_game_result_id' => $result->getAttribute('id'),
                        'guess_order'         => $order,
                    ],
                    [
                        'player_id' => $item['player_id'],
                    ]
                );
            }

            $result->setAttribute('guesses_count', count($sequence));

            $newlyWon = false;
            if ($firstCorrectIndex !== null && $result->getAttribute('won_at') === null) {
                $result->setAttribute('won_at', $firstCorrectDate ?? now());
                $newlyWon = true;
            }

            $result->save();

            if ($newlyWon) {
                $newAchievements = $this->achievements->handleGameWin($user, $result);
                $unlocked = $unlocked->merge($newAchievements);
            }
        }

        PendingGuess::where('anon_key', $anonKey)->delete();

        return $unlocked->unique('id')->values();
    }

    protected function makeAnonKey(Request $request): string
    {
        $ip = (string) $request->ip();
        return hash_hmac('sha256', $ip, config('app.key'));
    }
}
