<?php

namespace App\Services\Pvp;

use App\Models\PvpMatchPlayer;

/**
 * Resolves and validates PvP match participants.
 */
class PvpParticipantService
{
    /**
     * Get exactly two participant user ids ordered by seat.
     *
     * @param int $matchId Match identifier.
     *
     * @return array{0:int,1:int}
     */
    public function getTwoUserIds(int $matchId): array
    {
        $participants = PvpMatchPlayer::where('match_id', $matchId)->orderBy('seat')->get(['user_id']);

        if ($participants->count() !== 2) {
            abort(500, 'Invalid match players.');
        }

        $uids = $participants->pluck('user_id')->map(fn ($v) => (int) $v)->values()->all();

        if (count($uids) !== 2 || $uids[0] <= 0 || $uids[1] <= 0 || $uids[0] === $uids[1]) {
            abort(500, 'Invalid match players.');
        }

        return [$uids[0], $uids[1]];
    }

    /**
     * Get the opponent user id for a given user among the two participants.
     *
     * @param array{0:int,1:int} $uids Participants.
     * @param int                $userId Current user id.
     *
     * @return int
     */
    public function opponentOf(array $uids, int $userId): int
    {
        if ($uids[0] === $userId) {
            return $uids[1];
        }

        if ($uids[1] === $userId) {
            return $uids[0];
        }

        abort(403, 'Not a participant.');
    }
}
