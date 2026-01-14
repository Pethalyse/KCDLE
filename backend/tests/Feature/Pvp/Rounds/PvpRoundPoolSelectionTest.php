<?php

namespace Feature\Pvp\Rounds;

use App\Models\PvpMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpRoundPoolSelectionTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_rounds_are_random_unique_and_match_best_of_five(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic', 'whois', 'locked_infos', 'draft', 'reveal_race']);

        $this->pvpSeedMinimalKcdlePlayer();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 5])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 5])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $match = PvpMatch::findOrFail($matchId);
        $rounds = (array) $match->rounds;

        $this->assertCount(5, $rounds);

        $types = array_column($rounds, 'type');
        $this->assertCount(5, array_unique($types));

        foreach ($rounds as $t) {
            $this->assertTrue(in_array($t['type'], ['classic', 'whois', 'locked_infos', 'draft', 'reveal_race'], true));
        }
    }

    public function test_rounds_are_unique_and_match_best_of_three(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic', 'whois', 'locked_infos', 'draft', 'reveal_race']);

        $this->pvpSeedMinimalKcdlePlayer();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 3])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 3])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $match = PvpMatch::findOrFail($matchId);
        $rounds = (array) $match->rounds;

        $this->assertCount(3, $rounds);

        $types = array_column($rounds, 'type');
        $this->assertCount(3, array_unique($types));

        foreach ($rounds as $t) {
            $this->assertTrue(in_array($t['type'], ['classic', 'whois', 'locked_infos', 'draft', 'reveal_race'], true));
        }
    }
}
