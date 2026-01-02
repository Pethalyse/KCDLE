<?php

namespace Tests\Unit\Pvp;

use App\Models\Team;
use App\Services\Pvp\PvpHintNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PvpHintNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_country_and_teams(): void
    {
        $none = Team::create(['slug' => 'none', 'display_name' => 'None']);

        $n = app(PvpHintNormalizer::class);

        $out = $n->normalize([
            'country_code' => '',
            'current_team_id' => null,
            'previous_team_id' => 0,
            'age' => 10,
        ]);

        $this->assertSame('NN', $out['country_code']);
        $this->assertSame((int) $none->id, (int) $out['current_team_id']);
        $this->assertSame((int) $none->id, (int) $out['previous_team_id']);
        $this->assertSame(10, $out['age']);
    }
}
