<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\DailyGame;
use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\League;
use App\Models\LoldlePlayer;
use App\Models\Player;
use App\Models\Role;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KcdleAndLoldlePlayerModelTest extends TestCase
{
    use RefreshDatabase;

    protected function createCountry(): Country
    {
        return Country::firstOrCreate(
            ['code' => 'FR'],
            ['name' => 'France'],
        );
    }

    protected function createRole(): Role
    {
        return Role::firstOrCreate([
            'code'   => 1,
            'label' => 'MID',
        ]);
    }

    protected function createGame(): Game
    {
        return Game::firstOrCreate([
            'code'      => 'LOL',
            'name'      => 'League of Legends',
            'icon_slug' => 'LOL',
        ]);
    }

    protected function createTeam(): Team
    {
        $country = $this->createCountry();

        return Team::create([
            'slug'         => 'kc',
            'display_name' => 'Karmine Corp',
            'short_name'   => 'KC',
            'country_code' => $country->getAttribute('code'),
            'is_karmine_corp' => true,
        ]);
    }

    protected function createPlayer(string $slug): Player
    {
        $country = $this->createCountry();
        $role    = $this->createRole();

        return Player::create([
            'slug'         => $slug,
            'display_name' => strtoupper($slug),
            'country_code' => $country->getAttribute('code'),
            'birthdate'    => '2000-01-01',
            'role_id'      => $role->getAttribute('id'),
        ]);
    }

    public function test_kcdle_player_is_used_in_future_daily_games(): void
    {
        $game   = $this->createGame();
        $player = $this->createPlayer('p1');

        $kcdle = KcdlePlayer::create([
            'player_id'                 => $player->getAttribute('id'),
            'game_id'                   => $game->getAttribute('id'),
            'current_team_id'           => null,
            'previous_team_before_kc_id'=> null,
            'first_official_year'       => 2020,
            'trophies_count'            => 1,
            'active'                    => true,
        ]);

        $this->assertFalse($kcdle->isUsedInFutureDailyGames());

        DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $kcdle->getAttribute('id'),
            'selected_for_date'=> Carbon::today()->addDay(),
            'solvers_count'    => 0,
            'total_guesses'    => 0,
        ]);

        $this->assertTrue($kcdle->isUsedInFutureDailyGames());
        $this->assertTrue($kcdle->cannotDeactivate());
    }

    public function test_loldle_player_is_used_in_future_daily_games(): void
    {
        $game   = $this->createGame();
        $league = League::create([
            'code'    => 'LFL',
            'name'    => 'LFL',
            'game_id' => $game->getAttribute('id'),
        ]);

        $team   = $this->createTeam();
        $player = $this->createPlayer('p2');

        $loldle = LoldlePlayer::create([
            'league_id' => $league->getAttribute('id'),
            'player_id' => $player->getAttribute('id'),
            'team_id'   => $team->getAttribute('id'),
            'lol_role'  => 'MID',
            'season'    => '2025',
            'active'    => true,
        ]);

        $this->assertFalse($loldle->isUsedInFutureDailyGames());

        DailyGame::create([
            'game'             => 'lfldle',
            'player_id'        => $loldle->getAttribute('id'),
            'selected_for_date'=> Carbon::today()->addDay(),
            'solvers_count'    => 0,
            'total_guesses'    => 0,
        ]);

        $this->assertTrue($loldle->isUsedInFutureDailyGames());
        $this->assertTrue($loldle->cannotDeactivate());
    }
}
