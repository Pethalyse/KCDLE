<?php

namespace App\Services\Pvp;

use App\Models\PvpActiveMatchLock;
use App\Models\PvpMatch;
use App\Models\PvpMatchEvent;
use App\Models\PvpMatchPlayer;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Handles PvP match lifecycle write operations.
 *
 * This service is responsible for state transitions such as forfeit (manual leave) and AFK resolution,
 * and for normal match completion by points.
 */
class PvpMatchLifecycleService
{
    /**
     * Forfeit a match for a given participant.
     *
     * The match is finished immediately and the opponent is declared winner.
     *
     * @param PvpMatch $match Match instance.
     * @param int $userId Forfeiting user id.
     * @param string $reason Forfeit reason identifier (leave, afk).
     *
     * @return array{ok:bool, winner_user_id:int, match_id:int, reason:string}
     * @throws Throwable
     */
    public function forfeit(PvpMatch $match, int $userId, string $reason): array
    {
        $this->validateReason($reason);

        if ($reason === 'points') {
            abort(422, 'Invalid reason for forfeit.');
        }

        return DB::transaction(function () use ($match, $userId, $reason) {
            $match = PvpMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if ($match->status !== 'active') {
                abort(409, 'Match is not active.');
            }

            $players = PvpMatchPlayer::where('match_id', $match->id)->lockForUpdate()->get();

            $forfeiting = $players->firstWhere('user_id', $userId);
            if (! $forfeiting) {
                abort(403, 'Not a participant of this match.');
            }

            $opponent = $players->firstWhere('user_id', '!=', $userId);
            if (! $opponent) {
                abort(409, 'Opponent not found.');
            }

            $winnerUserId = (int) $opponent->user_id;

            $state = $match->state ?? [];
            $state['ended_reason'] = $reason;
            $state['forfeiting_user_id'] = $userId;
            $state['winner_user_id'] = $winnerUserId;

            $match->status = 'finished';
            $match->finished_at = now();
            $match->state = $state;
            $match->save();

            PvpActiveMatchLock::where('match_id', $match->id)->delete();

            PvpMatchEvent::create([
                'match_id' => $match->id,
                'user_id' => $userId,
                'type' => 'player_forfeited',
                'payload' => [
                    'reason' => $reason,
                    'winner_user_id' => $winnerUserId,
                ],
                'created_at' => now(),
            ]);

            PvpMatchEvent::create([
                'match_id' => $match->id,
                'user_id' => null,
                'type' => 'match_finished',
                'payload' => [
                    'winner_user_id' => $winnerUserId,
                    'reason' => $reason,
                ],
                'created_at' => now(),
            ]);

            return [
                'ok' => true,
                'winner_user_id' => $winnerUserId,
                'match_id' => $match->id,
                'reason' => $reason,
            ];
        });
    }

    /**
     * Finish a match normally by points (best-of winner).
     *
     * @param PvpMatch $match Match instance.
     * @param int $winnerUserId Winner user id.
     *
     * @return array{ok:bool, winner_user_id:int, match_id:int, reason:string}
     * @throws Throwable
     */
    public function finishByPoints(PvpMatch $match, int $winnerUserId): array
    {
        $this->validateReason('points');

        return DB::transaction(function () use ($match, $winnerUserId) {
            $match = PvpMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if ($match->status !== 'active') {
                abort(409, 'Match is not active.');
            }

            $players = PvpMatchPlayer::where('match_id', $match->id)->lockForUpdate()->get();
            if ($players->count() !== 2) {
                abort(500, 'Invalid match players.');
            }

            $winner = $players->firstWhere('user_id', $winnerUserId);
            if (! $winner) {
                abort(422, 'Winner must be a participant.');
            }

            $state = $match->state ?? [];
            $state['ended_reason'] = 'points';
            $state['winner_user_id'] = $winnerUserId;

            $match->status = 'finished';
            $match->finished_at = now();
            $match->state = $state;
            $match->save();

            PvpActiveMatchLock::where('match_id', $match->id)->delete();

            PvpMatchEvent::create([
                'match_id' => $match->id,
                'user_id' => null,
                'type' => 'match_finished',
                'payload' => [
                    'winner_user_id' => $winnerUserId,
                    'reason' => 'points',
                ],
                'created_at' => now(),
            ]);

            return [
                'ok' => true,
                'winner_user_id' => $winnerUserId,
                'match_id' => $match->id,
                'reason' => 'points',
            ];
        });
    }

    /**
     * Determine whether a match participant is considered AFK based on their last heartbeat timestamp.
     *
     * @param PvpMatchPlayer $player     Match participant.
     * @param int            $thresholdS AFK threshold in seconds.
     *
     * @return bool True when the player is AFK.
     */
    public function isAfk(PvpMatchPlayer $player, int $thresholdS): bool
    {
        if ($player->last_seen_at === null) {
            return true;
        }

        return $player->last_seen_at->lte(now()->subSeconds($thresholdS));
    }

    /**
     * Validate reason identifiers accepted by PvP lifecycle operations.
     *
     * @param string $reason Reason identifier.
     *
     * @return void
     */
    private function validateReason(string $reason): void
    {
        if (! in_array($reason, ['leave', 'afk', 'points'], true)) {
            abort(422, 'Invalid reason.');
        }
    }
}
