<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpClassicMatchFlowApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_classic_round_guess_completes_best_of_one_match(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic']);

        $secretId = $this->pvpSeedMinimalKcdlePlayer();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$matchId}")
            ->assertOk()
            ->assertJsonPath('id', $matchId)
            ->assertJsonPath('match_id', $matchId);

        $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$matchId}/round")
            ->assertOk()
            ->assertJsonPath('id', $matchId)
            ->assertJsonPath('round_type', 'classic');

        $this->actingAs($u1, 'sanctum')
            ->postJson("/api/pvp/matches/{$matchId}/round/action", [
                'action' => ['type' => 'guess', 'player_id' => $secretId],
            ])
            ->assertOk();

        $this->actingAs($u2, 'sanctum')
            ->postJson("/api/pvp/matches/{$matchId}/round/action", [
                'action' => ['type' => 'guess', 'player_id' => $secretId],
            ])
            ->assertOk();

        $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$matchId}")
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('best_of', 1);
    }
}
