<?php

namespace App\Services\Pvp\Rounds;

/**
 * Validates and extracts guess actions payload.
 */
class GuessActionPayloadService
{
    /**
     * Ensure the action is a guess action and return the guessed player id.
     *
     * @param array $action Action payload.
     *
     * @return int
     */
    public function requireGuessPlayerId(array $action): int
    {
        $type = (string) ($action['type'] ?? '');

        if ($type !== 'guess') {
            abort(422, 'Invalid action.');
        }

        $playerId = (int) ($action['player_id'] ?? 0);

        if ($playerId <= 0) {
            abort(422, 'Invalid player_id.');
        }

        return $playerId;
    }
}
