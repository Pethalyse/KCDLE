<?php

namespace Tests\Unit\Pvp;

use App\Models\KcdlePlayer;
use App\Models\Player;
use App\Models\Team;
use App\Services\Pvp\PvpHintNormalizer;
use App\Services\Pvp\Rounds\HintValueService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HintValueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_hint_value_age_returns_int(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01'));

        Team::create(['slug' => 'none', 'display_name' => 'None']);

        $player = Player::create([
            'slug' => 'p1',
            'display_name' => 'P1',
            'country_code' => 'FR',
            'birthdate' => '2000-01-01',
            'role_id' => null,
        ]);

        $wrapper = KcdlePlayer::create([
            'player_id' => $player->id,
            'game_id' => 1,
            'current_team_id' => null,
            'previous_team_before_kc_id' => null,
            'first_official_year' => 2018,
            'trophies_count' => 0,
            'active' => true,
        ])->fresh();

        $svc = app(HintValueService::class);

        $age = $svc->readHintValue($wrapper, 'age');
        $this->assertSame(26, $age);

        Carbon::setTestNow();
    }

    public function test_build_revealed_applies_normalization_country_and_team(): void
    {
        $none = Team::create(['slug' => 'none', 'display_name' => 'None']);
        Team::create(['slug' => 'kc', 'display_name' => 'KC']);

        $player = Player::create([
            'slug' => 'p2',
            'display_name' => 'P2',
            'country_code' => '',
            'birthdate' => null,
            'role_id' => null,
        ]);

        $wrapper = KcdlePlayer::create([
            'player_id' => $player->id,
            'game_id' => 1,
            'current_team_id' => null,
            'previous_team_before_kc_id' => 0,
            'first_official_year' => null,
            'trophies_count' => null,
            'active' => true,
        ]);

        $svc = app(HintValueService::class);

        $revealed = $svc->buildRevealed('kcdle', (int) $wrapper->id, ['country_code', 'current_team_id', 'previous_team_id']);

        $this->assertSame('NN', $revealed['country_code']);
        $this->assertSame((int) $none->id, (int) $revealed['current_team_id']);
        $this->assertSame((int) $none->id, (int) $revealed['previous_team_id']);
    }
}
