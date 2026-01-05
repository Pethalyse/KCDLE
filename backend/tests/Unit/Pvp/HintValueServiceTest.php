<?php

namespace Tests\Unit\Pvp;

use App\Services\Pvp\Rounds\HintValueService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class HintValueServiceTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_read_hint_value_age_returns_int(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01'));

        $this->pvpSeedCountry('FR', 'France');
        $this->pvpSeedNoneTeam();

        $svc = app(HintValueService::class);

        $playerK = $this->pvpCreatePlayer('p1', 'P1', 'FR', '2000-01-01');
        $wrapperK = $this->pvpCreateKcdleWrapper($playerK, firstOfficialYear: 2018);

        $ageK = $svc->readHintValue($wrapperK, 'age');
        $this->assertSame(26, $ageK);

        $playerL = $this->pvpCreatePlayer('p1l', 'P1L', 'FR', '2000-01-01');
        $league = $this->pvpSeedLeague('lec', 'LEC');
        $team = $this->pvpSeedTeam('t1', 'T1');
        $wrapperL = $this->pvpCreateLoldleWrapper($playerL, $league, $team, 'mid');

        $ageL = $svc->readHintValue($wrapperL, 'age');
        $this->assertSame(26, $ageL);

        Carbon::setTestNow();
    }

    public function test_build_revealed_applies_normalization_country_and_team(): void
    {
        $none = $this->pvpSeedNoneTeam();

        $player = $this->pvpCreatePlayer('p2', 'P2', null, null);
        $wrapper = $this->pvpCreateKcdleWrapper($player, currentTeam: null, previousTeam: null, firstOfficialYear: 2018, trophiesCount: 0);

        $svc = app(HintValueService::class);

        $revealed = $svc->buildRevealed('kcdle', (int) $wrapper->id, ['country_code', 'current_team_id', 'previous_team_id']);

        $this->assertSame('NN', $revealed['country_code']);
        $this->assertSame((int) $none->id, (int) $revealed['current_team_id']);
        $this->assertSame((int) $none->id, (int) $revealed['previous_team_id']);
    }

    public function test_build_revealed_loldle_normalizes_country_and_team(): void
    {
        $player = $this->pvpCreatePlayer('p3', 'P3', null, null);
        $league = $this->pvpSeedLeague('lec', 'LEC');
        $team = $this->pvpSeedTeam('t2', 'T2');
        $wrapper = $this->pvpCreateLoldleWrapper($player, $league, $team, 'mid');

        $svc = app(HintValueService::class);

        $revealed = $svc->buildRevealed('lecdle', (int) $wrapper->id, ['country_code', 'current_team_id']);

        $this->assertSame('NN', $revealed['country_code']);
        $this->assertSame((int) $team->id, (int) $revealed['current_team_id']);
    }
}
