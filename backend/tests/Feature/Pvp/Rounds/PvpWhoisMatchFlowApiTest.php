<?php

namespace Feature\Pvp\Rounds;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpWhoisMatchFlowApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['whois']);
        Config::set('pvp.disable_shuffle', true);
    }

    public function test_whois_round_choose_turn_then_guess_secret_finishes_match_best_of_one(): void
    {
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'whois');
        $match->state = array_replace_recursive((array) $match->state, [
            'chooser_user_id' => (int) $u1->id,
        ]);
        $match->save();

        $matchId = (int) $match->id;

        $r = $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$matchId}/round")
            ->assertOk()
            ->assertJsonPath('round_type', 'whois')
            ->assertJsonPath('round.can_choose_turn', true)
            ->assertJsonPath('round.turn_user_id', null)
            ->json();

        $candidateIds = (array) ($r['round']['candidate_ids'] ?? []);
        $this->assertCount(1, $candidateIds);
        $secretId = (int) $candidateIds[0];
        $this->assertGreaterThan(0, $secretId);

        $this->actingAs($u2, 'sanctum')
            ->postJson("/api/pvp/matches/{$matchId}/round/action", [
                'action' => ['type' => 'choose_turn', 'first_player_user_id' => (int) $u2->id],
            ])
            ->assertStatus(403);

        $this->actingAs($u1, 'sanctum')
            ->postJson("/api/pvp/matches/{$matchId}/round/action", [
                'action' => ['type' => 'choose_turn', 'first_player_user_id' => (int) $u1->id],
            ])
            ->assertOk();

        $this->actingAs($u1, 'sanctum')
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
