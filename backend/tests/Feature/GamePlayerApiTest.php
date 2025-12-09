<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\League;
use App\Models\LoldlePlayer;
use App\Models\Player;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamePlayerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function seedCommonData(): array
    {
        $country = Country::create(['code' => 'FR', 'name' => 'France']);
        $role    = Role::create(['code' => 1, 'label' => 'MID']);
        $game    = Game::create(['code' => 'LOL', 'name' => 'League', 'icon_slug' => 'LOL']);
        $team    = Team::create([
            'slug'         => 'kc',
            'display_name' => 'Karmine Corp',
            'short_name'   => 'KC',
            'country_code' => $country->code,
            'is_karmine_corp' => true,
        ]);

        return compact('country', 'role', 'game', 'team');
    }

    public function test_index_returns_404_for_unknown_game(): void
    {
        $response = $this->getJson('/api/unknown/players');
        $response->assertStatus(404);
    }

    public function test_index_returns_kcdle_players_only_active_by_default(): void
    {
        $data = $this->seedCommonData();

        $p1 = Player::create([
            'slug'         => 'p1',
            'display_name' => 'P1',
            'country_code' => $data['country']->getAttribute('code'),
            'birthdate'    => '2000-01-01',
            'role_id'      => $data['role']->getAttribute('code'),
        ]);

        $p2 = Player::create([
            'slug'         => 'p2',
            'display_name' => 'P2',
            'country_code' => $data['country']->getAttribute('code'),
            'birthdate'    => '2000-01-02',
            'role_id'      => $data['role']->getAttribute('code'),
        ]);

        KcdlePlayer::create([
            'player_id'                 => $p1->getAttribute('id'),
            'game_id'                   => $data['game']->getAttribute('id'),
            'current_team_id'           => $data['team']->getAttribute('id'),
            'previous_team_before_kc_id'=> null,
            'first_official_year'       => 2020,
            'trophies_count'            => 1,
            'active'                    => true,
        ]);

        KcdlePlayer::create([
            'player_id'                 => $p2->getAttribute('id'),
            'game_id'                   => $data['game']->getAttribute('id'),
            'current_team_id'           => $data['team']->getAttribute('id'),
            'previous_team_before_kc_id'=> null,
            'first_official_year'       => 2020,
            'trophies_count'            => 1,
            'active'                    => false,
        ]);

        $response = $this->getJson('/api/kcdle/players');

        $response->assertOk()
            ->assertJson([
                'game'   => 'kcdle',
                'active' => true,
            ]);

        $this->assertCount(1, $response->json('players'));
    }

    public function test_index_can_include_inactive_players_when_active_false(): void
    {
        $data = $this->seedCommonData();

        $p1 = Player::create([
            'slug'         => 'p3',
            'display_name' => 'P3',
            'country_code' => $data['country']->getAttribute('code'),
            'birthdate'    => '2000-01-01',
            'role_id'      => $data['role']->getAttribute('code'),
        ]);

        KcdlePlayer::create([
            'player_id'                 => $p1->getAttribute('id'),
            'game_id'                   => $data['game']->getAttribute('id'),
            'current_team_id'           => $data['team']->getAttribute('id'),
            'previous_team_before_kc_id'=> null,
            'first_official_year'       => 2020,
            'trophies_count'            => 1,
            'active'                    => false,
        ]);

        $response = $this->getJson('/api/kcdle/players?active=0');

        $response->assertOk();
        $this->assertCount(1, $response->json('players'));
    }

    public function test_index_returns_lfldle_players(): void
    {
        $data = $this->seedCommonData();

        $league = League::create([
            'code'    => 'LFL',
            'name'    => 'LFL',
            'game_id' => $data['game']->getAttribute('id'),
        ]);

        $player = Player::create([
            'slug'         => 'lfl-player',
            'display_name' => 'LFL Player',
            'country_code' => $data['country']->getAttribute('code'),
            'birthdate'    => '2000-01-01',
            'role_id'      => $data['role']->getAttribute('code'),
        ]);

        LoldlePlayer::create([
            'league_id' => $league->getAttribute('id'),
            'player_id' => $player->getAttribute('id'),
            'team_id'   => $data['team']->getAttribute('id'),
            'lol_role'  => 'MID',
            'season'    => '2025',
            'active'    => true,
        ]);

        $response = $this->getJson('/api/lfldle/players');

        $response->assertOk();
        $this->assertCount(1, $response->json('players'));
        $this->assertCount(1, $response->json('players'));
    }

    public function test_index_returns_lecdle_players(): void
    {
        $data = $this->seedCommonData();

        $league = League::create([
            'code'    => 'LEC',
            'name'    => 'LEC',
            'game_id' => $data['game']->getAttribute('id'),
        ]);

        $player = Player::create([
            'slug'         => 'lec-player',
            'display_name' => 'LEC Player',
            'country_code' => $data['country']->getAttribute('code'),
            'birthdate'    => '2000-01-01',
            'role_id'      => $data['role']->getAttribute('code'),
        ]);

        LoldlePlayer::create([
            'league_id' => $league->getAttribute('id'),
            'player_id' => $player->getAttribute('id'),
            'team_id'   => $data['team']->getAttribute('id'),
            'lol_role'  => 'MID',
            'season'    => '2025',
            'active'    => true,
        ]);

        $response = $this->getJson('/api/lecdle/players');

        $response->assertOk();
        $this->assertCount(1, $response->json('players'));
    }
}
