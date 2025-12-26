<?php

namespace App\Services\Pvp\Rounds;

use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use App\Models\Player;
use App\Services\Pvp\PvpHintNormalizer;
use Carbon\Carbon;
use Throwable;

readonly class HintValueService
{

    public function __construct(
        private PvpHintNormalizer $normalizer,
    ){}

    public function buildRevealed(string $game, int $secretId, array $keys): array
    {
        $wrapper = Player::resolvePlayerModel($game, $secretId);
        if (! $wrapper) {
            abort(500, 'Secret player not found.');
        }

        $out = [];
        foreach ($keys as $k) {
            $k = (string) $k;
            $out[$k] = $this->readHintValue($wrapper, $k);
        }

        return $this->normalizer->normalize($out);
    }

    public function readHintValue(KcdlePlayer|LoldlePlayer $wrapper, string $key): mixed
    {
        return match ($key) {
            'country_code' => $wrapper->getAttribute('player')?->getAttribute('country_code'),
            'role_id' => $wrapper->getAttribute('player')?->getAttribute('role_id'),
            'game_id' => $wrapper instanceof KcdlePlayer ? $wrapper->getAttribute('game_id') : null,
            'current_team_id' => $this->readCurrentTeamId($wrapper),
            'previous_team_id' => $wrapper instanceof KcdlePlayer ? $wrapper->getAttribute('previousTeam')?->getAttribute('id') : null,
            'trophies_count' => $wrapper instanceof KcdlePlayer ? $wrapper->getAttribute('trophies_count') : null,
            'first_official_year' => $wrapper instanceof KcdlePlayer ? $wrapper->getAttribute('first_official_year') : null,
            'age' => $this->readAge($wrapper),
            'lol_role' => $wrapper instanceof LoldlePlayer ? $wrapper->getAttribute('lol_role') : null,
            default => null,
        };
    }

    private function readCurrentTeamId(KcdlePlayer|LoldlePlayer $wrapper): ?int
    {
        if ($wrapper instanceof KcdlePlayer) {
            $id = $wrapper->getAttribute('currentTeam')?->getAttribute('id');
            return is_numeric($id) ? (int) $id : null;
        }

        $id = $wrapper->getAttribute('team')?->getAttribute('id');
        return is_numeric($id) ? (int) $id : null;
    }

    private function readAge(KcdlePlayer|LoldlePlayer $wrapper): ?int
    {
        $birthdate = $wrapper->getAttribute('player')?->getAttribute('birthdate');
        if (! $birthdate) {
            return null;
        }

        try {
            return Carbon::parse($birthdate)->age;
        } catch (Throwable) {
            return null;
        }
    }
}
