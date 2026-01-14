<?php

namespace App\Services\Pvp;

use App\Models\PvpActiveMatchLock;
use App\Models\PvpMatch;
use App\Models\PvpMatchEvent;
use App\Models\PvpMatchPlayer;
use App\Models\PvpQueueEntry;
use App\Models\User;
use App\Services\Pvp\Rounds\PvpRoundHandlerFactory;
use Illuminate\Support\Facades\DB;
use Throwable;

readonly class PvpMatchmakingService
{
    public function __construct(private PvpMatchService $matches, private PvpRoundHandlerFactory $factory)
    {
    }

    /**
     * Add a user to the PvP queue for a given game and best-of format and attempt to match immediately.
     *
     * If the user is already in an active match, this returns a reconnect payload instead of queuing.
     * If the user is already queued for another game, this operation is rejected.
     *
     * @param User $user Authenticated user joining the queue.
     * @param string $game Game identifier (kcdle, lecdle, lfldle).
     * @param int $bestOf Best-of format (1, 3, 5).
     *
     * @return array{status:string, match_id?:int}
     * @throws Throwable
     */
    public function joinQueue(User $user, string $game, int $bestOf): array
    {
        $this->validateGame($game);
        $this->validateBestOf($bestOf);

        $activeMatch = $this->matches->findActiveMatchForUser((int) $user->id);
        if ($activeMatch !== null) {
            return ['status' => 'in_match', 'match_id' => $activeMatch->id];
        }

        $existing = PvpQueueEntry::where('user_id', $user->id)->first();

        if ($existing !== null && $existing->game !== $game) {
            abort(409, 'You are already queued for another game.');
        }

        PvpQueueEntry::updateOrCreate(
            ['user_id' => $user->id],
            [
                'game' => $game,
                'best_of' => $bestOf,
                'created_at' => $existing?->created_at ?? now(),
            ]
        );

        $match = $this->tryMatch($game, $bestOf);

        if ($match !== null) {
            return ['status' => 'matched', 'match_id' => $match->id];
        }

        return ['status' => 'queued'];
    }

    /**
     * Remove a user from the PvP queue.
     *
     * @param User   $user Authenticated user leaving the queue.
     * @param string $game Game identifier.
     *
     * @return void
     */
    public function leaveQueue(User $user, string $game): void
    {
        $this->validateGame($game);

        PvpQueueEntry::where('user_id', $user->id)->delete();
    }

    /**
     * Attempt to create a match by pairing the two oldest queued players for the given game and best-of format.
     *
     * @param string $game Game identifier.
     * @param int $bestOf Best-of format.
     *
     * @return PvpMatch|null Newly created match if two players are available, otherwise null.
     * @throws Throwable
     */
    private function tryMatch(string $game, int $bestOf): ?PvpMatch
    {
        return DB::transaction(function () use ($game, $bestOf) {
            $entries = PvpQueueEntry::where('game', $game)
                ->where('best_of', $bestOf)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->limit(2)
                ->get();

            if ($entries->count() < 2) {
                return null;
            }

            $u1 = (int) $entries[0]->user_id;
            $u2 = (int) $entries[1]->user_id;

            if ($u1 === $u2) {
                PvpQueueEntry::where('id', $entries[0]->id)->delete();
                return null;
            }

            if ($this->matches->findActiveMatchForUser($u1) !== null) {
                PvpQueueEntry::where('id', $entries[0]->id)->delete();
                return null;
            }

            if ($this->matches->findActiveMatchForUser($u2) !== null) {
                PvpQueueEntry::where('id', $entries[1]->id)->delete();
                return null;
            }

            $roundPool = [];
            foreach ((array) config('pvp.round_pool', []) as $round) {
                $roundResolve = $this->factory->forType($round);
                $roundPool[] = [
                    'type' => $roundResolve->type(),
                    'name' => $roundResolve->name(),
                ];
            }
            if (count($roundPool) < $bestOf) {
                abort(500, 'PvP round pool is smaller than requested best-of format.');
            }

            if (!config('pvp.disable_shuffle', false)) {
                shuffle($roundPool);
            }
            $selectedRounds = array_values(array_slice($roundPool, 0, $bestOf));

            $match = PvpMatch::create([
                'game' => $game,
                'status' => 'active',
                'best_of' => $bestOf,
                'current_round' => 1,
                'rounds' => $selectedRounds,
                'state' => [
                    'round' => 1,
                    'round_type' => $selectedRounds[0]['type'],
                    'chooser_rule' => 'random_first_then_last_winner',
                    'chooser_user_id' => null,
                    'last_round_winner_user_id' => null,
                ],
                'started_at' => now(),
            ]);

            PvpMatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $u1,
                'seat' => 1,
                'points' => 0,
                'last_seen_at' => now(),
                'last_action_at' => now(),
            ]);

            PvpMatchPlayer::create([
                'match_id' => $match->id,
                'user_id' => $u2,
                'seat' => 2,
                'points' => 0,
                'last_seen_at' => now(),
                'last_action_at' => now(),
            ]);

            PvpActiveMatchLock::create([
                'user_id' => $u1,
                'match_id' => $match->id,
                'created_at' => now(),
            ]);

            PvpActiveMatchLock::create([
                'user_id' => $u2,
                'match_id' => $match->id,
                'created_at' => now(),
            ]);

            PvpMatchEvent::create([
                'match_id' => $match->id,
                'user_id' => null,
                'type' => 'match_created',
                'payload' => [
                    'game' => $game,
                    'best_of' => $bestOf,
                    'rounds' => $selectedRounds,
                ],
                'created_at' => now(),
            ]);

            PvpQueueEntry::whereIn('id', [$entries[0]->id, $entries[1]->id])->delete();

            return $match;
        });
    }

    /**
     * Validate game identifiers accepted by PvP.
     *
     * @param string $game Game identifier.
     *
     * @return void
     */
    private function validateGame(string $game): void
    {
        if (! in_array($game, ['kcdle', 'lfldle', 'lecdle'], true)) {
            abort(404, 'Unknown game.');
        }
    }

    /**
     * Validate best-of formats accepted by PvP.
     *
     * @param int $bestOf Best-of format.
     *
     * @return void
     */
    private function validateBestOf(int $bestOf): void
    {
        $allowed = (array) config('pvp.allowed_best_of', []);
        if (! in_array($bestOf, $allowed, true)) {
            abort(422, 'Invalid best-of format.');
        }
    }
}
