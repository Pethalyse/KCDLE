<?php

namespace Feature\Pvp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_non_participant_cannot_view_match_or_round_or_act(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic']);

        $secretId = $this->pvpSeedMinimalKcdlePlayer();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        $u3 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);

        $this->actingAs($u3, 'sanctum')->getJson("/api/pvp/matches/{$matchId}")->assertStatus(403);
        $this->actingAs($u3, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertStatus(403);
        $this->actingAs($u3, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertStatus(403);
    }
}
