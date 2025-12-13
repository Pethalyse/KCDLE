<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\User;
use App\Models\PendingGuess;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use App\Models\DailyGame;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PendingGuessService
{
    public function __construct(
        protected AchievementService $achievements,
        protected AnonKeyService $anonKeys
    ) {}

    /**
     * Import pending anonymous guesses for a user.
     *
     * This method:
     * - Generates the anonymous key from the request.
     * - Fetches all PendingGuess entries for that key.
     * - Groups guesses by daily_game_id.
     * - For each daily game:
     *     - Loads the DailyGame model.
     *     - Loads or creates a UserGameResult.
     *     - Reconstructs the chronological guess list:
     *          - ordered by created_at, then guess_order
     *          - deduplicated by player_id
     *     - Determines whether and when a correct guess occurred.
     *     - Updates UserGameResult (guesses_count, won_at).
     *     - Creates UserGuess records for each guess.
     *     - Calls AchievementService::handleGameWin() if newly won.
     * - Deletes the PendingGuess entries afterward.
     *
     * Returns a merged collection of achievements unlocked through all imported
     * daily games.
     *
     * @param User $user User who just authenticated.
     * @param Request $request HTTP request for IP-based anon key resolution.
     *
     * @return Collection<int, Achievement> Unlocked achievements.
     */
    public function import(User $user, Request $request): Collection
    {
        $anonKey = $this->anonKeys->fromRequest($request);

        $pending = PendingGuess::where('anon_key', $anonKey)
            ->orderBy('created_at')
            ->orderBy('guess_order')
            ->get();

        if ($pending->isEmpty()) {
            return collect();
        }

        $grouped = $pending->groupBy('daily_game_id');
        $unlocked = collect();

        foreach ($grouped as $dailyGameId => $guesses) {
            $daily = DailyGame::find($dailyGameId);
            if (!$daily) {
                continue;
            }

            $result = UserGameResult::firstOrCreate([
                'user_id'       => $user->getAttribute('id'),
                'daily_game_id' => $daily->getAttribute('id'),
                "game" => $daily->getAttribute('game'),
            ]);

            if($result->won_at !== null) {
                continue;
            }

            $sequence = $guesses
                ->sortBy(['created_at', 'guess_order'])
                ->unique('player_id')
                ->values();

            $firstCorrectIndex = $sequence->search(fn ($g) => $g->player_id === $daily->player_id);

            if ($firstCorrectIndex !== false) {
                $result->won_at = now();
                $result->guesses_count = $firstCorrectIndex + 1;
                $result->save();

                $newUnlocked = $this->achievements->handleGameWin($user, $result);

                $unlocked = $unlocked->merge(collect($newUnlocked));
            }


            foreach ($sequence as $idx => $g) {
                UserGuess::updateOrCreate(
                    [
                        'user_game_result_id' => $result->getAttribute('id'),
                        'guess_order'         => $idx + 1,
                    ],
                    [
                        'player_id' => $g->player_id,
                    ]
                );
            }
        }

        PendingGuess::where('anon_key', $anonKey)->delete();

        return $unlocked->unique('id')->values();
    }
}
