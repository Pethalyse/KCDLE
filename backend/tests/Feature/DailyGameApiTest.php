<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\DailyGame;
use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\Player;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyGameApiTest extends TestCase
{
    use RefreshDatabase;

    protected function createKcdlePlayer(): KcdlePlayer
    {
        $country = Country::create(['code' => 'FR', 'name' => 'France']);
        $role    = Role::create(['code' => 1, 'label' => 'MID']);
        $game    = Game::create(['code' => 'LOL', 'name' => 'League', 'icon_slug' => 'LOL']);

        $player = Player::create([
            'slug'         => 'test-player',
            'display_name' => 'Test Player',
            'country_code' => $country->getAttribute('code'),
            'birthdate'    => '2000-01-01',
            'role_id'      => $role->getAttribute('id'),
        ]);

        return KcdlePlayer::create([
            'player_id'                 => $player->getAttribute('id'),
            'game_id'                   => $game->getAttribute('id'),
            'current_team_id'           => null,
            'previous_team_before_kc_id'=> null,
            'first_official_year'       => 2020,
            'trophies_count'            => 4,
            'active'                    => true,
        ]);
    }

    public function test_show_returns_404_for_unknown_game(): void
    {
        $response = $this->getJson('/api/unknown/daily');

        $response->assertStatus(404);
    }

    public function test_show_returns_404_when_no_daily_for_today(): void
    {
        $response = $this->getJson('/api/kcdle/daily');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'No daily game configured for today.',
            ]);
    }

    public function test_show_returns_daily_data_for_today(): void
    {
        $kcdlePlayer = $this->createKcdlePlayer();

        $daily = DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $kcdlePlayer->getAttribute('id'),
            'selected_for_date'=> Carbon::today(),
            'solvers_count'    => 4,
            'total_guesses'    => 10,
        ]);

        $response = $this->getJson('/api/daily/kcdle');

        $response->assertOk()
            ->assertJson([
                'id'              => $daily->getAttribute('id'),
                'game'            => 'kcdle',
                'game_label'      => 'KCDLE',
                'solvers_count'   => 4,
                'total_guesses'   => 10,
            ]);

        $this->assertEquals(2.5, $response->json('average_guesses'));
    }
}
