<?php

namespace Feature\Pvp\Rounds;

use App\Models\PvpMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpDraftMatchFlowApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_draft_full_flow_choose_order_pick_hints_then_guess_finishes_match(): void
    {
        $this->pvpConfigureForSingleRound('draft', 1);

        $this->pvpSeedMinimalKcdlePlayer('a', 'A');

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $roundU1 = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk()->json();
        $chooserId = (int) ($roundU1['chooser_user_id'] ?? 0);
        $this->assertTrue(in_array($chooserId, [(int) $u1->id, (int) $u2->id], true));

        $chooser = $chooserId === (int) $u1->id ? $u1 : $u2;

        $this->actingAs($chooser, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'choose_draft_order', 'first_picker_user_id' => (int) $u1->id],
        ])->assertOk();

        $r = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk()->json();
        $allowed = (array) ($r['round']['allowed_keys'] ?? []);
        $this->assertGreaterThanOrEqual(4, count($allowed));
        $keys = array_values(array_unique(array_slice($allowed, 0, 4)));
        $this->assertCount(4, $keys);

        for ($i = 0; $i < 4; $i++) {
            $r = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk()->json();
            $this->assertSame('draft', (string) ($r['round']['phase'] ?? ''));
            $turnId = (int) ($r['round']['turn_user_id'] ?? 0);
            $this->assertTrue(in_array($turnId, [(int) $u1->id, (int) $u2->id], true));

            $actor = $turnId === (int) $u1->id ? $u1 : $u2;

            $this->actingAs($actor, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
                'action' => ['type' => 'pick_hint', 'key' => $keys[$i]],
            ])->assertOk();
        }

        $roundU1 = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk()->json();
        $roundU2 = $this->actingAs($u2, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk()->json();

        $this->assertSame('guess', (string) ($roundU1['round']['phase'] ?? ''));
        $this->assertSame('guess', (string) ($roundU2['round']['phase'] ?? ''));
        $this->assertCount(2, (array) ($roundU1['round']['revealed'] ?? []));
        $this->assertCount(2, (array) ($roundU2['round']['revealed'] ?? []));

        $match = PvpMatch::findOrFail($matchId);
        $secretId = (int) (($match->state['round_data']['draft']['secret_player_id'] ?? 0));
        $this->assertGreaterThan(0, $secretId);

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk();

        $finish = $this->actingAs($u2, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk()->json();

        $this->assertTrue((bool) ($finish['match_finished'] ?? false));

        $match = PvpMatch::findOrFail($matchId);
        $this->assertSame('finished', (string) $match->status);
        $this->assertSame('points', (string) ($match->state['ended_reason'] ?? ''));
        $this->assertTrue(in_array((int) ($match->state['winner_user_id'] ?? 0), [(int) $u1->id, (int) $u2->id], true));
    }

    public function test_draft_not_your_turn_pick_hint_returns_409(): void
    {
        $this->pvpConfigureForSingleRound('draft', 1);

        $this->pvpSeedMinimalKcdlePlayer('a', 'A');

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $round = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk()->json();
        $chooserId = (int) ($round['chooser_user_id'] ?? 0);
        $chooser = $chooserId === (int) $u1->id ? $u1 : $u2;

        $this->actingAs($chooser, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'choose_draft_order', 'first_picker_user_id' => (int) $u1->id],
        ])->assertOk();

        $r = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk()->json();
        $allowed = (array) ($r['round']['allowed_keys'] ?? []);
        $this->assertNotEmpty($allowed);
        $key = (string) ($allowed[0] ?? '');

        $turnId = (int) ($r['round']['turn_user_id'] ?? 0);
        $notTurn = $turnId === (int) $u1->id ? $u2 : $u1;

        $this->actingAs($notTurn, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'pick_hint', 'key' => $key],
        ])->assertStatus(409);
    }
}
