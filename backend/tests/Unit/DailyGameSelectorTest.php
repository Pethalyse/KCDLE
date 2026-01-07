<?php

namespace Tests\Unit;

use App\Models\DailyGame;
use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\Player;
use App\Services\DailyGameSelector;
use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionException;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;
use App\Models\Country;
use App\Models\Role;

class DailyGameSelectorTest extends TestCase
{
    use RefreshDatabase;

    protected DailyGameSelector $selector;

    /**
     * @throws BindingResolutionException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = $this->app->make(DailyGameSelector::class);
    }

    /**
     * Crée le minimum de données pour KCDLE :
     * - un pays
     * - un rôle
     * - un jeu
     * - un joueur
     * - un KcdlePlayer
     */
    protected function createBasicKcdlePlayer(array $overrides = []): KcdlePlayer
    {
        $country = Country::firstOrCreate([
            'code' => 'FR',
            'name' => 'France',
        ]);

        $role = Role::firstOrCreate([
            'code'   => 1,
            'label' => 'MID',
        ]);

        $game = Game::firstOrCreate([
            'code'      => 'LOL',
            'name'      => 'League of Legends',
            'icon_slug' => 'LOL',
        ]);

        $player = Player::create([
            'slug'         => $overrides['player_slug'] ?? 'player-' . uniqid(),
            'display_name' => $overrides['display_name'] ?? 'Player',
            'country_code' => $country->code,
            'birthdate'    => $overrides['birthdate'] ?? '2000-01-01',
            'role_id'      => $role->id,
        ]);

        return KcdlePlayer::create([
            'player_id'                 => $player->id,
            'game_id'                   => $game->id,
            'current_team_id'           => null,
            'previous_team_before_kc_id'=> null,
            'first_official_year'       => $overrides['first_official_year'] ?? 2020,
            'trophies_count'            => $overrides['trophies_count'] ?? 0,
            'active'                    => $overrides['active'] ?? true,
        ]);
    }

    public function test_select_for_game_returns_existing_daily_if_present(): void
    {
        $date = Carbon::today();

        $kcdlePlayer = $this->createBasicKcdlePlayer();

        $existing = DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $kcdlePlayer->getAttribute("id"),
            'selected_for_date'=> $date,
            'solvers_count'    => 10,
            'total_guesses'    => 40,
        ]);

        $result = $this->selector->selectForGame('kcdle', $date);

        $this->assertTrue($existing->is($result));
        $this->assertEquals(1, DailyGame::where('game', 'kcdle')
            ->whereDate('selected_for_date', $date)
            ->count());
    }

    public function test_select_for_game_throws_when_no_eligible_players(): void
    {
        $this->expectException(RuntimeException::class);

        $this->selector->selectForGame('kcdle', Carbon::today());
    }

    public function test_select_for_game_creates_new_daily_for_kcdle(): void
    {
        mt_srand(1234);

        $p1 = $this->createBasicKcdlePlayer([
            'player_slug'        => 'player-1',
            'display_name'       => 'Player 1',
            'first_official_year'=> 2018,
            'trophies_count'     => 3,
        ]);

        $p2 = $this->createBasicKcdlePlayer([
            'player_slug'        => 'player-2',
            'display_name'       => 'Player 2',
            'first_official_year'=> 2022,
            'trophies_count'     => 1,
        ]);

        DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $p1->getAttribute("id"),
            'selected_for_date'=> Carbon::yesterday(),
            'solvers_count'    => 5,
            'total_guesses'    => 20,
        ]);

        $date = Carbon::today()->addDay();

        $result = $this->selector->selectForGame('kcdle', $date);

        $this->assertEquals('kcdle', $result->game);
        $this->assertTrue(in_array($result->player_id, [$p1->getAttribute("id"), $p2->getAttribute("id")], true));
        $this->assertEquals($date->toDateString(), $result->selected_for_date->toDateString());
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_stats_for_game_returns_empty_collection_when_no_daily(): void
    {
        $refMethod = new ReflectionMethod(DailyGameSelector::class, 'getStatsForGame');
        $refMethod->setAccessible(true);

        $stats = $refMethod->invoke($this->selector, 'kcdle');

        $this->assertCount(0, $stats);
    }

    /**
     * @throws ReflectionException
     */
    public function test_get_stats_for_game_returns_aggregated_stats(): void
    {
        $p1 = $this->createBasicKcdlePlayer(['player_slug' => 'p1']);
        $p2 = $this->createBasicKcdlePlayer(['player_slug' => 'p2']);

        DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $p1->getAttribute("id"),
            'selected_for_date'=> Carbon::parse('2025-01-01'),
            'solvers_count'    => 3,
            'total_guesses'    => 10,
        ]);

        DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $p1->getAttribute("id"),
            'selected_for_date'=> Carbon::parse('2025-01-10'),
            'solvers_count'    => 2,
            'total_guesses'    => 5,
        ]);

        DailyGame::create([
            'game'             => 'kcdle',
            'player_id'        => $p2->getAttribute("id"),
            'selected_for_date'=> Carbon::parse('2025-01-05'),
            'solvers_count'    => 1,
            'total_guesses'    => 2,
        ]);

        $refMethod = new ReflectionMethod(DailyGameSelector::class, 'getStatsForGame');
        $refMethod->setAccessible(true);

        $stats = collect($refMethod->invoke($this->selector, 'kcdle'));

        $this->assertCount(2, $stats);

        $s1 = $stats->get($p1->getKey());
        $s2 = $stats->get($p2->getKey());

        $this->assertNotNull($s1, 'Stats manquantes pour le joueur 1');
        $this->assertNotNull($s2, 'Stats manquantes pour le joueur 2');

        $this->assertEquals(2, $s1['times_selected']);
        $this->assertEquals('2025-01-10', $s1['last_selected_at']->toDateString());

        $this->assertEquals(1, $s2['times_selected']);
        $this->assertEquals('2025-01-05', $s2['last_selected_at']->toDateString());
    }
}
