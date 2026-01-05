<?php

namespace App\Services\Pvp;

use App\Models\PvpMatch;
use App\Services\Dle\GamePlayerService;

/**
 * Selects secret players for PvP rounds.
 */
readonly class PvpSecretPlayerService
{
    public function __construct(private GamePlayerService $players)
    {
    }

    /**
     * Pick a secret player id for a given match game.
     *
     * @param PvpMatch $match Match instance.
     *
     * @return int
     */
    public function pickSecretId(PvpMatch $match): int
    {
        $secretId = $this->players->randomPlayerId((string) $match->game, true);

        if ($secretId <= 0) {
            abort(500, 'Unable to select a secret player.');
        }

        return $secretId;
    }
}
