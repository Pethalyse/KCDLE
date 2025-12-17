<?php

namespace App\Services\Pvp\Rounds;

use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use App\Models\Player;

/**
 * Reads and builds hint values for a given game wrapper player.
 */
class HintValueService
{
    /**
     * Build revealed hints for a secret player for a given set of keys.
     *
     * @param string $game     Game identifier.
     * @param int    $secretId Secret wrapper id.
     * @param array  $keys     Hint keys.
     *
     * @return array<string,mixed>
     */
    public function buildRevealed(string $game, int $secretId, array $keys): array
    {
        $wrapper = Player::resolvePlayerModel($game, $secretId);
        if (!$wrapper) {
            abort(500, 'Secret player not found.');
        }

        $out = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            $out[$k] = $this->readHintValue($wrapper, $k);
        }

        return $out;
    }

    /**
     * Read a hint value from the wrapper for a supported key.
     *
     * @param KcdlePlayer|LoldlePlayer  $wrapper Player wrapper.
     * @param string $key     Hint key.
     *
     * @return mixed
     */
    public function readHintValue(KcdlePlayer|LoldlePlayer $wrapper, string $key): mixed
        {
            return match ($key) {
                'country_code' => $wrapper->getAttribute('player')?->getAttribute('country_code'),
                'role_id' => $wrapper->getAttribute('player')?->getAttribute('role_id'),
                'game_id' => $wrapper->getAttribute('game_id'),
                'current_team_id' => $wrapper->getAttribute('currentTeam')?->getAttribute('id'),
                'previous_team_id' => $wrapper->getAttribute('previousTeam')?->getAttribute('id'),
                'trophies_count' => $wrapper->getAttribute('trophies_count'),
                'first_official_year' => $wrapper->getAttribute('first_official_year'),
                default => null,
            };
        }
    }
