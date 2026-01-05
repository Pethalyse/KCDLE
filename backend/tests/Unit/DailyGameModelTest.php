<?php

namespace Tests\Unit;

use App\Models\Country;
use App\Models\DailyGame;
use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\Player;
use App\Models\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DailyGameModelTest extends TestCase
{
    use RefreshDatabase;

    protected function createMinimalKcdlePlayer(): KcdlePlayer
    {
        $country = Country::create([
            'code' => 'FR',
            'name' => 'France',
        ]);

        $role = Role::create([
            'code'   => 1,
            'label' => 'MID',
        ]);

        $game = Game::create([
            'code'      => 'LOL',
            'name'      => 'League of Legends',
            'icon_slug' => 'LOL',
        ]);

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
            'trophies_count'            => 2,
            'active'                    => true,
        ]);
    }

    public function test_average_guesses_returns_null_when_no_solvers(): void
    {
        $kcdlePlayer = $this->createMinimalKcdlePlayer();

        $daily = DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $kcdlePlayer->getAttribute('id'),
            'selected_for_date'=> Carbon::today(),
            'solvers_count'    => 0,
            'total_guesses'    => 0,
        ]);

        $this->assertNull($daily->average_guesses);
    }

    public function test_average_guesses_returns_ratio_when_solvers_exist(): void
    {
        $kcdlePlayer = $this->createMinimalKcdlePlayer();

        $daily = DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $kcdlePlayer->getAttribute('id'),
            'selected_for_date'=> Carbon::today(),
            'solvers_count'    => 4,
            'total_guesses'    => 10,
        ]);

        $this->assertEquals(2.5, $daily->average_guesses);
    }

    public function test_game_label_mapping(): void
    {
        $kcdlePlayer = $this->createMinimalKcdlePlayer();

        $daily = DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $kcdlePlayer->getAttribute('id'),
            'selected_for_date'=> Carbon::today()->addDay(),
            'solvers_count'    => 0,
            'total_guesses'    => 0,
        ]);

        $this->assertEquals('KCDLE', $daily->game_label);
    }

    public function test_cannot_delete_past_or_current_daily(): void
    {
        $this->expectException(RuntimeException::class);

        $kcdlePlayer = $this->createMinimalKcdlePlayer();

        $daily = DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $kcdlePlayer->getAttribute('id'),
            'selected_for_date'=> Carbon::today(),
            'solvers_count'    => 0,
            'total_guesses'    => 0,
        ]);

        $daily->delete();
    }

    public function test_can_delete_future_daily(): void
    {
        $kcdlePlayer = $this->createMinimalKcdlePlayer();

        $daily = DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $kcdlePlayer->getAttribute('id'),
            'selected_for_date'=> Carbon::today()->addDays(2),
            'solvers_count'    => 0,
            'total_guesses'    => 0,
        ]);

        $this->assertTrue($daily->delete());
        $this->assertDatabaseMissing('daily_games', ['id' => $daily->id]);
    }
}
