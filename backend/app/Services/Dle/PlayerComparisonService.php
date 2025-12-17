<?php

namespace App\Services\Dle;

use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Computes DLE-style comparisons between a secret player and a guessed player.
 *
 * This service centralizes the comparison logic currently used by daily guess flows
 * so it can also be reused by PvP rounds (classic round, etc.).
 */
class PlayerComparisonService
{
    /**
     * Compare secret vs guess based on the game and return comparison payload.
     *
     * @param KcdlePlayer|LoldlePlayer  $secret Secret wrapper model (KcdlePlayer|LoldlePlayer).
     * @param KcdlePlayer|LoldlePlayer  $guess  Guess wrapper model (KcdlePlayer|LoldlePlayer).
     * @param string $game   Game identifier (kcdle|lecdle|lfldle).
     *
     * @return array{correct:bool, fields:array<string,int|null>}
     */
    public function comparePlayers(KcdlePlayer|LoldlePlayer $secret, KcdlePlayer|LoldlePlayer $guess, string $game): array
    {
        return match ($game) {
            'kcdle' => ($secret instanceof KcdlePlayer && $guess instanceof KcdlePlayer)
                ? $this->compareKcdlePlayers($secret, $guess)
                : ['correct' => false, 'fields' => []],

            'lfldle', 'lecdle' => ($secret instanceof LoldlePlayer && $guess instanceof LoldlePlayer)
                ? $this->compareLoldlePlayers($secret, $guess)
                : ['correct' => false, 'fields' => []],

            default => ['correct' => false, 'fields' => []],
        };
    }

    /**
     * Compare two KcdlePlayer instances and compute field-level hints.
     *
     * @param KcdlePlayer $secret Secret wrapper.
     * @param KcdlePlayer $guess  Guess wrapper.
     *
     * @return array{correct:bool, fields:array<string,int|null>}
     */
    public function compareKcdlePlayers(KcdlePlayer $secret, KcdlePlayer $guess): array
    {
        $secretPlayer = $secret->getAttribute('player');
        $guessPlayer  = $guess->getAttribute('player');

        $slug = $this->eq($secretPlayer?->getAttribute('slug'), $guessPlayer?->getAttribute('slug'));
        $country = $this->eq($secretPlayer?->getAttribute('country_code'), $guessPlayer?->getAttribute('country_code'));
        $role = $this->eq($secretPlayer?->getAttribute('role_id'), $guessPlayer?->getAttribute('role_id'));
        $gameField = $this->eq($secret->getAttribute('game_id'), $guess->getAttribute('game_id'));

        $currentTeam = $this->eq(
            $secret->getAttribute('currentTeam')?->getAttribute('id'),
            $guess->getAttribute('currentTeam')?->getAttribute('id')
        );

        $previousTeam = $this->eq(
            $secret->getAttribute('previousTeam')?->getAttribute('id'),
            $guess->getAttribute('previousTeam')?->getAttribute('id')
        );

        $birthday = $this->cmpDate(
            $secretPlayer?->getAttribute('birthdate'),
            $guessPlayer?->getAttribute('birthdate')
        );

        $firstOfficialYear = $this->cmpNumber(
            $secret->getAttribute('first_official_year'),
            $guess->getAttribute('first_official_year')
        );

        $trophies = $this->cmpNumber(
            $secret->getAttribute('trophies_count'),
            $guess->getAttribute('trophies_count')
        );

        $correct = $slug === 1;

        return [
            'correct' => $correct,
            'fields' => [
                'country' => $country,
                'birthday' => $birthday,
                'game' => $gameField,
                'first_official_year' => $firstOfficialYear,
                'trophies' => $trophies,
                'previous_team' => $previousTeam,
                'current_team' => $currentTeam,
                'role' => $role,
                'slug' => $slug,
            ],
        ];
    }

    /**
     * Compare two LoldlePlayer instances and compute field-level hints.
     *
     * @param LoldlePlayer $secret Secret wrapper.
     * @param LoldlePlayer $guess  Guess wrapper.
     *
     * @return array{correct:bool, fields:array<string,int|null>}
     */
    public function compareLoldlePlayers(LoldlePlayer $secret, LoldlePlayer $guess): array
    {
        $secretPlayer = $secret->getAttribute('player');
        $guessPlayer  = $guess->getAttribute('player');

        $slug = $this->eq($secretPlayer?->getAttribute('slug'), $guessPlayer?->getAttribute('slug'));
        $country = $this->eq($secretPlayer?->getAttribute('country_code'), $guessPlayer?->getAttribute('country_code'));

        $birthday = $this->cmpDate(
            $secretPlayer?->getAttribute('birthdate'),
            $guessPlayer?->getAttribute('birthdate')
        );

        $team = $this->eq(
            $secret->getAttribute('team_id'),
            $guess->getAttribute('team_id')
        );

        $lolRole = $this->eq(
            $secret->getAttribute('lol_role'),
            $guess->getAttribute('lol_role')
        );

        $correct = $slug === 1;

        return [
            'correct' => $correct,
            'fields' => [
                'country' => $country,
                'birthday' => $birthday,
                'team' => $team,
                'lol_role' => $lolRole,
                'slug' => $slug,
            ],
        ];
    }

    /**
     * Compare two values for strict equality.
     *
     * @param mixed $a Left-hand value.
     * @param mixed $b Right-hand value.
     *
     * @return int 1 if values are strictly equal, 0 otherwise.
     */
    public function eq(mixed $a, mixed $b): int
    {
        return (int) ($a === $b);
    }

    /**
     * Compare two numeric values and return a directional hint.
     *
     * @param float|null $secret Secret numeric value.
     * @param float|null $guess  Guessed numeric value.
     *
     * @return int|null 1 if equal, 0 if secret > guess, -1 if secret < guess, null if missing.
     */
    public function cmpNumber(?float $secret, ?float $guess): ?int
    {
        if ($secret === null || $guess === null) {
            return null;
        }

        if ($secret === $guess) {
            return 1;
        }

        return $secret < $guess ? -1 : 0;
    }

    /**
     * Compare two dates based on their age in years.
     *
     * @param string|DateTimeInterface|null $secret Secret date or null.
     * @param string|DateTimeInterface|null $guess  Guessed date or null.
     *
     * @return int|null 1 if equal age, 0 if secret older, -1 if secret younger, null if missing.
     */
    public function cmpDate(null|string|DateTimeInterface $secret, null|string|DateTimeInterface $guess): ?int
    {
        if ($secret === null || $guess === null) {
            return null;
        }

        $s = $secret instanceof Carbon ? $secret->age : Carbon::parse($secret)->age;
        $g = $guess instanceof Carbon ? $guess->age : Carbon::parse($guess)->age;

        if ($s === $g) {
            return 1;
        }

        return $s < $g ? -1 : 0;
    }
}
