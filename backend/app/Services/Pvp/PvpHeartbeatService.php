<?php

namespace App\Services\Pvp;

use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Handles PvP heartbeat updates for match participants.
 *
 * Heartbeats refresh last_seen_at to support AFK detection and presence UX.
 */
class PvpHeartbeatService
{
    /**
     * Update the participant heartbeat for a given match.
     *
     * @param PvpMatch $match Match instance.
     * @param int $userId Authenticated user id.
     *
     * @return array{ok:bool, server_time:string}
     * @throws Throwable
     */
    public function heartbeat(PvpMatch $match, int $userId): array
    {
        return DB::transaction(function () use ($match, $userId) {
            $match = PvpMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if ($match->status !== 'active') {
                abort(409, 'Match is not active.');
            }

            $player = PvpMatchPlayer::where('match_id', $match->id)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (! $player) {
                abort(403, 'Not a participant of this match.');
            }

            $player->last_seen_at = now();
            $player->save();

            return [
                'ok' => true,
                'server_time' => now()->toISOString(),
            ];
        });
    }
}
