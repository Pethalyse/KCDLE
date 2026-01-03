<?php

namespace Feature\Pvp\Rounds;

use App\Models\PvpMatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpWhoisAskFlowApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_choose_turn_then_ask_then_guess_finishes_match(): void
    {
        $this->pvpSeedMinimalKcdlePlayer('only', 'Only');

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'whois', 1);

        $r0 = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$match->id}/round")->assertOk()->json();
        $chooserId = (int) ($r0['chooser_user_id'] ?? 0);
        $chooser = $chooserId === (int) $u2->id ? $u2 : $u1;

        $this->actingAs($chooser, 'sanctum')->postJson("/api/pvp/matches/{$match->id}/round/action", [
            'action' => ['type' => 'choose_turn', 'first_player_user_id' => (int) $chooser->id],
        ])->assertOk();

        $r1 = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$match->id}/round")->assertOk()->json();
        $candidateIds = (array) ($r1['round']['candidate_ids'] ?? []);
        $this->assertCount(1, $candidateIds);
        $secretId = (int) $candidateIds[0];

        $turnUserId = (int) ($r1['round']['turn_user_id'] ?? 0);
        $turnUser = $turnUserId === (int) $u2->id ? $u2 : $u1;

        $this->actingAs($turnUser, 'sanctum')->postJson("/api/pvp/matches/{$match->id}/round/action", [
            'action' => [
                'type' => 'ask',
                'question' => [
                    'key' => 'trophies_count',
                    'op' => 'gt',
                    'value' => 0,
                ],
            ],
        ])->assertOk();

        $r2 = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$match->id}/round")->assertOk()->json();
        $turnUserId2 = (int) ($r2['round']['turn_user_id'] ?? 0);
        $turnUser2 = $turnUserId2 === (int) $u2->id ? $u2 : $u1;

        $this->actingAs($turnUser2, 'sanctum')->postJson("/api/pvp/matches/{$match->id}/round/action", [
            'action' => [
                'type' => 'guess',
                'player_id' => $secretId,
            ],
        ])->assertOk();

        $this->assertSame('finished', (string) (PvpMatch::findOrFail($match->id)->status));
    }
}
