<?php

namespace Tests\Feature;

use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpChooserRuleTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_round_two_chooser_is_last_round_winner(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic', 'draft', 'locked_infos']);

        $idA = $this->pvpSeedMinimalKcdlePlayer('a', 'A');
        $idB = $this->pvpSeedMinimalKcdlePlayer('b', 'B');

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 3])->assertOk();
        $resp = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 3])->assertOk()->json();

        $matchId = (int) ($resp['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $match = PvpMatch::findOrFail($matchId);
        $this->assertSame(1, (int) $match->current_round);

        $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}/round")->assertOk();

        $m1 = PvpMatch::findOrFail($matchId);
        $secretId = (int) ((
            $m1->state['round_data']['classic']['secret_player_id']
            ?? $m1->state['round_data']['draft']['secret_player_id']
            ?? $m1->state['round_data']['locked_infos']['secret_player_id']
            ?? 0));
        $this->assertTrue(in_array($secretId, [$idA, $idB], true));

        $wrongId = ($secretId === $idA) ? $idB : $idA;

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk();

        $this->actingAs($u2, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $wrongId],
        ])->assertOk();

        $this->actingAs($u2, 'sanctum')->postJson("/api/pvp/matches/{$matchId}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk();

        $m2 = PvpMatch::findOrFail($matchId);
        $this->assertSame(2, (int) $m2->current_round);

        $winnerRow = PvpMatchPlayer::where('match_id', $matchId)->orderByDesc('points')->first();
        $this->assertNotNull($winnerRow);
        $winnerUserId = (int) $winnerRow->user_id;

        $payload = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$matchId}")->assertOk()->json();
        $this->assertSame($winnerUserId, (int) ($payload['chooser_user_id'] ?? 0));
    }
}
