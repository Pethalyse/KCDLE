<?php

namespace Tests\Feature;

use App\Models\PvpMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpLockedInfosMatchFlowApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_locked_infos_tie_breaker_fewer_guesses_wins(): void
    {
        $this->pvpConfigureForSingleRound('locked_infos', 1);

        $this->pvpSeedMinimalKcdlePlayer('a', 'A');

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk();

        $match = PvpMatch::findOrFail($matchId);
        $secretId = (int) (($match->state['round_data']['locked_infos']['secret_player_id'] ?? 0));
        $this->assertGreaterThan(0, $secretId);

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId + 999],
        ])->assertOk();

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk();

        $finish = $this->actingAs($u2, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk()->json();

        $this->assertTrue((bool) ($finish['match_finished'] ?? false));
        $this->assertSame((int) $u2->id, (int) ($finish['finish']['winner_user_id'] ?? 0));

        $match = PvpMatch::findOrFail($matchId);
        $this->assertSame('finished', (string) $match->status);
        $this->assertSame('points', (string) ($match->state['ended_reason'] ?? ''));
        $this->assertSame((int) $u2->id, (int) ($match->state['winner_user_id'] ?? 0));
    }
}
