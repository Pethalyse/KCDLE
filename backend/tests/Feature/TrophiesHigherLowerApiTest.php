<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\Player;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the KCDLE trophies Higher/Lower solo mode API.
 */
class TrophiesHigherLowerApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Seed the minimal shared domain data required to create KCDLE players.
     *
     * @return array<string, mixed>
     */
    protected function seedCommonData(): array
    {
        $country = Country::create(['code' => 'FR', 'name' => 'France']);
        $role    = Role::create(['code' => 1, 'label' => 'MID']);
        $game    = Game::create(['code' => 'LOL', 'name' => 'League', 'icon_slug' => 'LOL']);
        $team    = Team::create([
            'slug'            => 'kc',
            'display_name'    => 'Karmine Corp',
            'short_name'      => 'KC',
            'country_code'    => $country->code,
            'is_karmine_corp' => true,
        ]);

        return compact('country', 'role', 'game', 'team');
    }

    /**
     * Ensure starting the game returns two players with hidden trophy values.
     *
     * @return void
     */
    public function test_can_start_a_session(): void
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
            'player_id'                  => $p1->getAttribute('id'),
            'game_id'                    => $data['game']->getAttribute('id'),
            'current_team_id'            => $data['team']->getAttribute('id'),
            'previous_team_before_kc_id' => null,
            'first_official_year'        => 2020,
            'trophies_count'             => 1,
            'active'                     => true,
        ]);

        KcdlePlayer::create([
            'player_id'                  => $p2->getAttribute('id'),
            'game_id'                    => $data['game']->getAttribute('id'),
            'current_team_id'            => $data['team']->getAttribute('id'),
            'previous_team_before_kc_id' => null,
            'first_official_year'        => 2020,
            'trophies_count'             => 2,
            'active'                     => true,
        ]);

        $response = $this->postJson('/api/games/kcdle/trophies-higher-lower/start');

        $response->assertOk();
        $this->assertNotEmpty($response->json('session_id'));
        $this->assertNotEmpty($response->json('left.id'));
        $this->assertNotEmpty($response->json('right.id'));
        $this->assertNull($response->json('left.trophies_count'));
        $this->assertNull($response->json('right.trophies_count'));
    }

    /**
     * Ensure a correct guess reveals trophy values and returns a next state.
     *
     * @return void
     */
    public function test_correct_guess_returns_next_state_and_increments_score(): void
    {
        $data = $this->seedCommonData();

        $players = [];
        foreach ([1, 5, 3] as $i => $trophies) {
            $p = Player::create([
                'slug'         => 'p' . ($i + 1),
                'display_name' => 'P' . ($i + 1),
                'country_code' => $data['country']->getAttribute('code'),
                'birthdate'    => '2000-01-0' . ($i + 1),
                'role_id'      => $data['role']->getAttribute('code'),
            ]);

            $players[] = KcdlePlayer::create([
                'player_id'                  => $p->getAttribute('id'),
                'game_id'                    => $data['game']->getAttribute('id'),
                'current_team_id'            => $data['team']->getAttribute('id'),
                'previous_team_before_kc_id' => null,
                'first_official_year'        => 2020,
                'trophies_count'             => $trophies,
                'active'                     => true,
            ]);
        }

        $start = $this->postJson('/api/games/kcdle/trophies-higher-lower/start')->assertOk()->json();

        $leftId = (int) $start['left']['id'];
        $rightId = (int) $start['right']['id'];

        $left = KcdlePlayer::findOrFail($leftId);
        $right = KcdlePlayer::findOrFail($rightId);

        $choice = (int) $left->getAttribute('trophies_count') >= (int) $right->getAttribute('trophies_count') ? 'left' : 'right';

        $guess = $this->postJson('/api/games/kcdle/trophies-higher-lower/guess', [
            'session_id' => $start['session_id'],
            'choice' => $choice,
        ]);

        $guess->assertOk()
            ->assertJson([
                'session_id' => $start['session_id'],
                'correct' => true,
                'game_over' => false,
                'score' => 1,
                'round' => 1,
            ]);

        $this->assertNotNull($guess->json('next'));
        $this->assertSame(2, $guess->json('next.round'));
        $this->assertSame(1, $guess->json('next.score'));
        $this->assertNotNull($guess->json('next.left.trophies_count'));
        $this->assertNull($guess->json('next.right.trophies_count'));
    }

    /**
     * Ensure a wrong guess ends the session and subsequent guesses return 404.
     *
     * @return void
     */
    public function test_wrong_guess_ends_session(): void
    {
        $data = $this->seedCommonData();

        $p1 = Player::create([
            'slug'         => 'a',
            'display_name' => 'A',
            'country_code' => $data['country']->getAttribute('code'),
            'birthdate'    => '2000-01-01',
            'role_id'      => $data['role']->getAttribute('code'),
        ]);

        $p2 = Player::create([
            'slug'         => 'b',
            'display_name' => 'B',
            'country_code' => $data['country']->getAttribute('code'),
            'birthdate'    => '2000-01-02',
            'role_id'      => $data['role']->getAttribute('code'),
        ]);

        $p3 = Player::create([
            'slug'         => 'c',
            'display_name' => 'C',
            'country_code' => $data['country']->getAttribute('code'),
            'birthdate'    => '2000-01-03',
            'role_id'      => $data['role']->getAttribute('code'),
        ]);

        KcdlePlayer::create([
            'player_id'                  => $p1->getAttribute('id'),
            'game_id'                    => $data['game']->getAttribute('id'),
            'current_team_id'            => $data['team']->getAttribute('id'),
            'previous_team_before_kc_id' => null,
            'first_official_year'        => 2020,
            'trophies_count'             => 10,
            'active'                     => true,
        ]);

        KcdlePlayer::create([
            'player_id'                  => $p2->getAttribute('id'),
            'game_id'                    => $data['game']->getAttribute('id'),
            'current_team_id'            => $data['team']->getAttribute('id'),
            'previous_team_before_kc_id' => null,
            'first_official_year'        => 2020,
            'trophies_count'             => 1,
            'active'                     => true,
        ]);

        KcdlePlayer::create([
            'player_id'                  => $p3->getAttribute('id'),
            'game_id'                    => $data['game']->getAttribute('id'),
            'current_team_id'            => $data['team']->getAttribute('id'),
            'previous_team_before_kc_id' => null,
            'first_official_year'        => 2020,
            'trophies_count'             => 2,
            'active'                     => true,
        ]);

        $start = $this->postJson('/api/games/kcdle/trophies-higher-lower/start')->assertOk()->json();

        $leftId = (int) $start['left']['id'];
        $rightId = (int) $start['right']['id'];

        $left = KcdlePlayer::findOrFail($leftId);
        $right = KcdlePlayer::findOrFail($rightId);

        $wrongChoice = (int) $left->getAttribute('trophies_count') >= (int) $right->getAttribute('trophies_count') ? 'right' : 'left';

        $guess = $this->postJson('/api/games/kcdle/trophies-higher-lower/guess', [
            'session_id' => $start['session_id'],
            'choice' => $wrongChoice,
        ]);

        $guess->assertOk()
            ->assertJson([
                'session_id' => $start['session_id'],
                'correct' => false,
                'game_over' => true,
                'next' => null,
            ]);

        $this->postJson('/api/games/kcdle/trophies-higher-lower/guess', [
            'session_id' => $start['session_id'],
            'choice' => $wrongChoice,
        ])->assertStatus(404);
    }
}
