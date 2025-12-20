<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpIncludeStateApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_heartbeat_include_state_returns_heartbeat_and_state(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic']);

        $this->pvpSeedMinimalKcdlePlayer();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);

        $this->actingAs($u1, 'sanctum')
            ->postJson("/api/pvp/matches/{$matchId}/heartbeat?include_state=1")
            ->assertOk()
            ->assertJsonStructure([
                'heartbeat',
                'state' => ['id', 'match_id', 'status', 'best_of', 'current_round'],
            ]);
    }

    public function test_events_include_state_returns_events_and_state(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic']);

        $secretId = $this->pvpSeedMinimalKcdlePlayer();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);

        $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk();
        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk();
        $this->actingAs($u2, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk();

        $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$matchId}/events?after_id=0&include_state=1")
            ->assertOk()
            ->assertJsonStructure([
                'events',
                'state' => ['id', 'match_id', 'status', 'best_of', 'current_round'],
            ]);
    }
}
