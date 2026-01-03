<?php

namespace Tests\Support;

use App\Models\Country;
use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\League;
use App\Models\LoldlePlayer;
use App\Models\Player;
use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Config;

trait PvpTestHelper
{
    protected function pvpConfigureForSingleRound(string $roundType, int $bestOf = 1): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.default_best_of', 5);
        Config::set('pvp.round_pool', [$roundType]);
        Config::set('pvp.afk_seconds', 90);
        Config::set('pvp.idle_seconds', 300);
    }

    protected function pvpSeedGame(string $code, ?string $name = null): Game
    {
        $name ??= strtoupper($code);
        return Game::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'icon_slug' => null],
        );
    }

    protected function pvpSeedCountry(string $code = 'FR', string $name = 'France'): Country
    {
        $code = strtoupper(trim($code));
        return Country::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name],
        );
    }

    protected function pvpSeedTeam(
        string $slug = 'none',
        string $displayName = 'None',
        ?string $countryCode = null,
        bool $isKarmineCorp = false
    ): Team {
        $countryCode = $countryCode !== null && trim($countryCode) !== '' ? strtoupper(trim($countryCode)) : null;
        if ($countryCode !== null) {
            $this->pvpSeedCountry($countryCode, $countryCode);
        }

        return Team::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'display_name' => $displayName,
                'short_name' => null,
                'country_code' => $countryCode,
                'is_karmine_corp' => $isKarmineCorp,
            ],
        );
    }

    protected function pvpSeedNoneTeam(): Team
    {
        return $this->pvpSeedTeam('none', 'None');
    }

    protected function pvpSeedLeague(string $code = 'LEC', string $name = 'lec', ?Game $game = null): League
    {
        $game ??= $this->pvpSeedGame('lecdle', 'LECDLE');

        return League::query()->firstOrCreate(
            ['code' => strtoupper($code)],
            ['name' => $name, 'game_id' => (int) $game->id],
        );
    }

    protected function pvpCreatePlayer(
        string $slug = 'tester',
        string $displayName = 'Tester',
        ?string $countryCode = null,
        ?string $birthdate = null,
        ?int $roleId = null
    ): Player {
        $countryCode = $countryCode !== null && trim($countryCode) !== '' ? strtoupper(trim($countryCode)) : null;
        if ($countryCode !== null) {
            $this->pvpSeedCountry($countryCode, $countryCode);
        }

        return Player::query()->create([
            'slug' => $slug,
            'display_name' => $displayName,
            'country_code' => $countryCode,
            'birthdate' => $birthdate,
            'role_id' => $roleId,
        ]);
    }

    protected function pvpCreateKcdleWrapper(
        Player $player,
        ?Game $game = null,
        ?Team $currentTeam = null,
        ?Team $previousTeam = null,
        int $firstOfficialYear = 2020,
        int $trophiesCount = 0,
        bool $active = true
    ): KcdlePlayer {
        $game ??= $this->pvpSeedGame('kcdle', 'KCDLE');

        return KcdlePlayer::query()->create([
            'player_id' => (int) $player->id,
            'game_id' => (int) $game->id,
            'current_team_id' => $currentTeam?->id,
            'previous_team_before_kc_id' => $previousTeam?->id,
            'first_official_year' => $firstOfficialYear,
            'trophies_count' => $trophiesCount,
            'active' => $active,
        ])->fresh();
    }

    protected function pvpCreateLoldleWrapper(
        Player $player,
        ?League $league = null,
        ?Team $team = null,
        string $lolRole = 'mid',
        ?string $season = null,
        bool $active = true
    ): LoldlePlayer {
        $league ??= $this->pvpSeedLeague();
        $team ??= $this->pvpSeedTeam('t1', 'T1');

        return LoldlePlayer::query()->create([
            'league_id' => (int) $league->id,
            'player_id' => (int) $player->id,
            'team_id' => (int) $team->id,
            'lol_role' => $lolRole,
            'season' => $season,
            'active' => $active,
        ])->fresh();
    }

    protected function pvpSeedMinimalKcdlePlayer(string $slug = 'tester', string $name = 'Tester'): int
    {
        $this->pvpSeedNoneTeam();
        $player = $this->pvpCreatePlayer($slug, $name);
        $wrapper = $this->pvpCreateKcdleWrapper($player);

        return (int) $wrapper->id;
    }

    protected function pvpSeedMinimalLoldlePlayer(
        string $slug = 'tester',
        string $name = 'Tester',
        string $leagueCode = 'lec',
        string $teamSlug = 't1',
        string $lolRole = 'mid'
    ): int {
        $player = $this->pvpCreatePlayer($slug, $name);
        $league = $this->pvpSeedLeague(strtoupper($leagueCode), $leagueCode);
        $team = $this->pvpSeedTeam($teamSlug, strtoupper($teamSlug));
        $wrapper = $this->pvpCreateLoldleWrapper($player, $league, $team, $lolRole);

        return (int) $wrapper->id;
    }

    protected function pvpCreateMatch(string $game, string|array $roundType, int $bestOf = 1, ?User $u1 = null, ?User $u2 = null): array
    {
        $u1 ??= User::factory()->create();
        $u2 ??= User::factory()->create();

        if(is_string($roundType)) $roundType = [$roundType];

        $match = PvpMatch::query()->create([
            'game' => $game,
            'status' => 'active',
            'best_of' => $bestOf,
            'current_round' => 1,
            'rounds' => $roundType,
            'state' => [
                'round' => 1,
                'round_type' => $roundType[0],
                'chooser_rule' => 'random_first_then_last_winner',
                'chooser_user_id' => null,
                'last_round_winner_user_id' => null,
            ],
            'started_at' => now(),
        ]);

        PvpMatchPlayer::query()->create([
            'match_id' => $match->id,
            'user_id' => $u1->id,
            'seat' => 1,
            'points' => 0,
            'last_seen_at' => now(),
            'last_action_at' => now(),
        ]);

        PvpMatchPlayer::query()->create([
            'match_id' => $match->id,
            'user_id' => $u2->id,
            'seat' => 2,
            'points' => 0,
            'last_seen_at' => now(),
            'last_action_at' => now(),
        ]);

        return [$match->fresh(), $u1, $u2];
    }
}
