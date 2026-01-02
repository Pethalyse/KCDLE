<?php

namespace Tests\Feature;

use App\Models\PvpMatch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpRevealRaceMatchFlowApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_reveal_race_wrong_guess_blocks_temporarily_then_can_guess_after_cooldown(): void
    {
        $this->pvpConfigureForSingleRound('reveal_race', 1);

        $this->pvpSeedMinimalKcdlePlayer('a', 'A');

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk();

        $match = PvpMatch::findOrFail($matchId);
        $secretId = (int) (($match->state['round_data']['reveal_race']['secret_player_id'] ?? 0));
        $this->assertGreaterThan(0, $secretId);

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId + 999],
        ])->assertOk();

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertStatus(409);

        $this->travel(6)->seconds();

        $finish = $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk()->json();

        $this->assertTrue((bool) ($finish['match_finished'] ?? false));

        $match = PvpMatch::findOrFail($matchId);
        $this->assertSame('finished', (string) $match->status);
        $this->assertSame('points', (string) ($match->state['ended_reason'] ?? ''));
        $this->assertSame((int) $u1->id, (int) ($match->state['winner_user_id'] ?? 0));
    }

    public function test_reveal_race_tick_emits_reveal_event_after_interval(): void
    {
        $this->pvpConfigureForSingleRound('reveal_race', 1);

        $this->pvpSeedMinimalKcdlePlayer('a', 'A');

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}")->assertOk();

        $match = PvpMatch::findOrFail($matchId);
        $startedAt = (string) (($match->state['round_data']['reveal_race']['started_at'] ?? ''));
        $this->assertNotSame('', $startedAt);

        Carbon::setTestNow(Carbon::parse($startedAt)->addSeconds(9));

        $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}")->assertOk();

        $events = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/events?after_id=0&limit=50")->assertOk()->json();
        $rows = (array) ($events['events'] ?? []);

        $found = false;
        foreach ($rows as $e) {
            if (($e['type'] ?? null) === 'reveal_race_reveal') {
                $found = true;
                break;
            }
        }

        Carbon::setTestNow();

        $this->assertTrue($found);
    }
}
