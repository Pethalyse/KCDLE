<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\DailyGame;
use App\Models\PendingGuess;
use App\Models\User;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Service responsible for importing and merging pending guesses into a user's history.
 *
 * Pending guesses can be stored under:
 * - an IP-based anonymous key (browser not logged-in)
 * - a Discord-based anonymous key (bot usage when the Discord account is not linked)
 *
 * When a user authenticates (or links Discord), this service merges the pending guesses
 * with existing authenticated guesses while keeping consistency:
 * - Merge both sources chronologically by created_at (with deterministic tie-breakers).
 * - Prevent duplicates by player_id (keep the earliest occurrence).
 * - If the daily is solved (either on the site or in pending guesses), discard any guesses
 *   that happened after the earliest win timestamp.
 * - Rebuild guess_order sequentially.
 * - Update UserGameResult (guesses_count, won_at).
 *
 * It also triggers achievements when a win becomes effective after the merge.
 */
class PendingGuessService
{
    /**
     * @param AchievementService $achievements Achievement service.
     * @param AnonKeyService $anonKeys Anonymous key generator.
     */
    public function __construct(
        protected AchievementService $achievements,
        protected AnonKeyService $anonKeys
    ) {
    }

    /**
     * Import pending guesses based on the request's IP anonymous key.
     *
     * @param User $user Authenticated user.
     * @param Request $request Current request.
     *
     * @return Collection<int, Achievement> Unlocked achievements.
     * @throws Throwable
     */
    public function import(User $user, Request $request): Collection
    {
        $anonKey = $this->anonKeys->fromRequest($request);

        return $this->importFromAnonKey($user, $anonKey);
    }

    /**
     * Import pending guesses for a Discord identity.
     *
     * @param User $user Authenticated user.
     * @param string $discordId Discord user id.
     *
     * @return Collection<int, Achievement> Unlocked achievements.
     * @throws Throwable
     */
    public function importDiscord(User $user, string $discordId): Collection
    {
        $anonKey = $this->anonKeys->fromValue('discord:' . $discordId);

        return $this->importFromAnonKey($user, $anonKey);
    }

    /**
     * Import all pending guesses stored for a specific anonymous key.
     *
     * @param User $user Authenticated user.
     * @param string $anonKey Anonymous key.
     *
     * @return Collection<int, Achievement> Unlocked achievements.
     * @throws Throwable
     */
    public function importFromAnonKey(User $user, string $anonKey): Collection
    {
        $pending = PendingGuess::query()
            ->where('anon_key', $anonKey)
            ->orderBy('created_at')
            ->orderBy('guess_order')
            ->get();

        if ($pending->isEmpty()) {
            return collect();
        }

        $grouped = $pending->groupBy('daily_game_id');
        $unlocked = collect();

        foreach ($grouped as $dailyGameId => $guesses) {
            $daily = DailyGame::query()->find($dailyGameId);
            if (!$daily instanceof DailyGame) {
                continue;
            }

            DB::transaction(function () use ($user, $daily, $guesses, &$unlocked): void {
                $result = UserGameResult::query()->firstOrCreate(
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
                $existingWonAt = $wasWonBefore ? Carbon::parse($result->getAttribute('won_at')) : null;

                $existing = UserGuess::query()
                    ->where('user_game_result_id', $result->getAttribute('id'))
                    ->orderBy('created_at')
                    ->orderBy('guess_order')
                    ->get(['player_id', 'guess_order', 'created_at']);

                /** @var array<int, array{player_id:int, at:Carbon, source:int, order:int}> $items */
                $items = [];

                foreach ($existing as $g) {
                    $items[] = [
                        'player_id' => (int) $g->getAttribute('player_id'),
                        'at' => Carbon::parse($g->getAttribute('created_at')),
                        'source' => 0,
                        'order' => (int) $g->getAttribute('guess_order'),
                    ];
                }

                foreach ($guesses as $g) {
                    $items[] = [
                        'player_id' => (int) $g->getAttribute('player_id'),
                        'at' => Carbon::parse($g->getAttribute('created_at')),
                        'source' => 1,
                        'order' => (int) $g->getAttribute('guess_order'),
                    ];
                }

                usort($items, function (array $a, array $b): int {
                    $ta = $a['at']->getTimestamp();
                    $tb = $b['at']->getTimestamp();

                    if ($ta !== $tb) {
                        return $ta <=> $tb;
                    }

                    if ((int) $a['source'] !== (int) $b['source']) {
                        return ((int) $a['source']) <=> ((int) $b['source']);
                    }

                    return ((int) $a['order']) <=> ((int) $b['order']);
                });

                $seen = [];
                $sequence = [];

                foreach ($items as $it) {
                    $pid = (int) $it['player_id'];
                    if (isset($seen[$pid])) {
                        continue;
                    }

                    $seen[$pid] = true;
                    $sequence[] = $it;
                }

                $secretId = (int) $daily->getAttribute('player_id');

                $secretAt = null;
                foreach ($sequence as $it) {
                    if ((int) $it['player_id'] === $secretId) {
                        $secretAt = $it['at'];
                        break;
                    }
                }

                $cutoff = null;
                if ($existingWonAt instanceof Carbon) {
                    $cutoff = $existingWonAt;
                }

                if ($secretAt instanceof Carbon) {
                    $cutoff = $cutoff instanceof Carbon ? ($secretAt->lessThan($cutoff) ? $secretAt : $cutoff) : $secretAt;
                }

                if ($cutoff instanceof Carbon) {
                    $sequence = array_values(array_filter($sequence, fn (array $it) => $it['at']->lessThanOrEqualTo($cutoff)));

                    $secretIndex = null;
                    foreach ($sequence as $idx => $it) {
                        if ((int) $it['player_id'] === $secretId) {
                            $secretIndex = (int) $idx;
                            break;
                        }
                    }

                    if ($secretIndex !== null) {
                        $sequence = array_slice($sequence, 0, $secretIndex + 1);
                    }
                }

                $secretIndexFinal = null;
                foreach ($sequence as $idx => $it) {
                    if ((int) $it['player_id'] === $secretId) {
                        $secretIndexFinal = (int) $idx;
                        break;
                    }
                }

                $solvedNow = $cutoff instanceof Carbon;
                $guessesCount = $secretIndexFinal !== null ? $secretIndexFinal + 1 : count($sequence);

                $result->setAttribute('game', $daily->getAttribute('game'));
                $result->setAttribute('guesses_count', $guessesCount);
                $result->setAttribute('won_at', $solvedNow ? $cutoff : null);
                $result->save();

                DB::table('user_guesses')
                    ->where('user_game_result_id', $result->getAttribute('id'))
                    ->delete();

                if (count($sequence) > 0) {
                    $rows = [];

                    foreach ($sequence as $idx => $it) {
                        $ts = $it['at'];
                        $rows[] = [
                            'user_game_result_id' => $result->getAttribute('id'),
                            'guess_order' => $idx + 1,
                            'player_id' => (int) $it['player_id'],
                            'created_at' => $ts,
                            'updated_at' => $ts,
                        ];
                    }

                    DB::table('user_guesses')->insert($rows);
                }

                if (!$wasWonBefore && $solvedNow) {
                    $newUnlocked = $this->achievements->handleGameWin($user, $result);
                    $unlocked = $unlocked->merge(collect($newUnlocked));
                }
            });
        }

        PendingGuess::query()->where('anon_key', $anonKey)->delete();

        return $unlocked->unique('id')->values();
    }
}
