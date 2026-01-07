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
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class GameGuessApiTest extends TestCase
{
    use RefreshDatabase;

    protected function seedKcdleSetup(): array
    {
        $country = Country::create(['code' => 'FR', 'name' => 'France']);
        $role    = Role::create(['code' => 1, 'label' => 'MID']);
        $game    = Game::create(['code' => 'LOL', 'name' => 'League', 'icon_slug' => 'LOL']);

        $secretPlayer = Player::create([
            'slug'         => 'secret-player',
            'display_name' => 'Secret Player',
            'country_code' => $country->getAttribute('code'),
            'birthdate'    => '2000-01-01',
            'role_id'      => $role->getAttribute('id'),
        ]);

        $guessPlayer = Player::create([
            'slug'         => 'guess-player',
            'display_name' => 'Guess Player',
            'country_code' => $country->getAttribute('code'),
            'birthdate'    => '2000-02-02',
            'role_id'      => $role->getAttribute('id'),
        ]);

        $secretKcdle = KcdlePlayer::create([
            'player_id'                 => $secretPlayer->getAttribute('id'),
            'game_id'                   => $game->getAttribute('id'),
            'current_team_id'           => null,
            'previous_team_before_kc_id'=> null,
            'first_official_year'       => 2020,
            'trophies_count'            => 3,
            'active'                    => true,
        ]);

        $guessKcdle = KcdlePlayer::create([
            'player_id'                 => $guessPlayer->getAttribute('id'),
            'game_id'                   => $game->getAttribute('id'),
            'current_team_id'           => null,
            'previous_team_before_kc_id'=> null,
            'first_official_year'       => 2021,
            'trophies_count'            => 1,
            'active'                    => true,
        ]);

        return compact('secretKcdle', 'guessKcdle');
    }

    public function test_store_returns_404_for_unknown_game(): void
    {
        $response = $this->postJson('/api/games/unknown/guess', [
            'player_id' => 1,
            'guesses'   => 1,
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson(['message' => 'Unknown game.']);
    }

    public function test_store_returns_404_when_no_daily_for_today(): void
    {
        $response = $this->postJson('/api/games/kcdle/guess', [
            'player_id' => 1,
            'guesses'   => 1,
        ]);

        $response->assertStatus(Response::HTTP_NOT_FOUND)
            ->assertJson(['message' => 'No daily game configured for today.']);
    }

    public function test_store_returns_422_when_invalid_player(): void
    {
        $setup = $this->seedKcdleSetup();

        DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $setup['secretKcdle']->getAttribute('id'),
            'selected_for_date'=> Carbon::today(),
            'solvers_count'    => 0,
            'total_guesses'    => 0,
        ]);

        $response = $this->postJson('/api/games/kcdle/guess', [
            'player_id' => 9999,
            'guesses'   => 1,
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJson(['message' => 'Invalid player.']);
    }

    public function test_store_returns_correct_result_when_guess_is_exact(): void
    {
        $setup = $this->seedKcdleSetup();

        $daily = DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $setup['secretKcdle']->getAttribute('id'),
            'selected_for_date'=> Carbon::today(),
            'solvers_count'    => 0,
            'total_guesses'    => 0,
        ]);

        $response = $this->postJson('/api/games/kcdle/guess', [
            'player_id' => $setup['secretKcdle']->getAttribute('id'),
            'guesses'   => 5,
        ]);

        $response->assertOk();
        $this->assertTrue($response->json('correct'));

        $daily->refresh();
        $this->assertEquals(1, $daily->solvers_count);
        $this->assertEquals(5, $daily->total_guesses);
    }

    public function test_store_returns_fields_comparison_when_guess_is_wrong(): void
    {
        $setup = $this->seedKcdleSetup();

        $daily = DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $setup['secretKcdle']->getAttribute('id'),
            'selected_for_date'=> Carbon::today(),
            'solvers_count'    => 0,
            'total_guesses'    => 0,
        ]);

        $response = $this->postJson('/api/games/kcdle/guess', [
            'player_id' => $setup['guessKcdle']->getAttribute('id'),
            'guesses'   => 2,
        ]);

        $response->assertOk();

        $this->assertFalse($response->json('correct'));
        $this->assertIsArray($response->json('comparison'));
        $this->assertIsArray($response->json('comparison.fields'));
        $this->assertArrayHasKey('country', $response->json('comparison.fields'));
        $this->assertArrayHasKey('trophies', $response->json('comparison.fields'));

        $daily->refresh();
        $this->assertEquals(0, $daily->solvers_count);
        $this->assertEquals(0, $daily->total_guesses);
    }
}
