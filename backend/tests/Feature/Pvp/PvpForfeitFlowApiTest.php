<?php

namespace Feature\Pvp;

use App\Models\PvpMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpForfeitFlowApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_leave_finishes_match_and_emits_match_finished_event(): void
    {
        $this->pvpConfigureForSingleRound('classic', 1);

        $this->pvpSeedMinimalKcdlePlayer('a', 'A');

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $leave = $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/leave")->assertOk()->json();
        $this->assertSame('leave', (string) ($leave['reason'] ?? ''));
        $this->assertSame((int) $u2->id, (int) ($leave['winner_user_id'] ?? 0));

        $match = PvpMatch::findOrFail($matchId);
        $this->assertSame('finished', (string) $match->status);
        $this->assertSame('leave', (string) ($match->state['ended_reason'] ?? ''));
        $this->assertSame((int) $u2->id, (int) ($match->state['winner_user_id'] ?? 0));
        $this->assertSame((int) $u1->id, (int) ($match->state['forfeiting_user_id'] ?? 0));

        $events = $this->actingAs($u2, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/events?after_id=0&limit=50")->assertOk()->json();
        $rows = (array) ($events['events'] ?? []);

        $hasForfeit = false;
        $hasFinished = false;

        foreach ($rows as $e) {
            if (($e['type'] ?? null) === 'player_forfeited') {
                $hasForfeit = true;
            }
            if (($e['type'] ?? null) === 'match_finished') {
                $hasFinished = true;
            }
        }

        $this->assertTrue($hasForfeit);
        $this->assertTrue($hasFinished);
    }

    public function test_cannot_leave_finished_match_returns_409(): void
    {
        $this->pvpConfigureForSingleRound('classic', 1);

        $this->pvpSeedMinimalKcdlePlayer('a', 'A');

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/leave")->assertOk();
        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/leave")->assertStatus(409);
    }
}
