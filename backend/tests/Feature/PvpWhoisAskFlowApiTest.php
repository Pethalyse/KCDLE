<?php

namespace Tests\Feature;

use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpWhoisAskFlowApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_choose_turn_then_ask_then_guess_finishes_match(): void
    {
        $secretId = $this->pvpSeedMinimalKcdlePlayer();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $match = PvpMatch::create([
            'game' => 'kcdle',
            'status' => 'active',
            'best_of' => 1,
            'current_round' => 1,
            'rounds' => ['whois'],
            'state' => [
                'round' => 1,
                'round_type' => 'whois',
                'chooser_user_id' => $u1->id,
            ],
            'started_at' => now(),
        ]);

        PvpMatchPlayer::insert([
            ['match_id' => $match->id, 'user_id' => $u1->id, 'seat' => 1],
            ['match_id' => $match->id, 'user_id' => $u2->id, 'seat' => 2],
        ]);

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$match->id}/round/action", [
            'action' => ['type' => 'choose_turn', 'first_player_user_id' => $u1->id],
        ])->assertOk();

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$match->id}/round/action", [
            'action' => [
                'type' => 'ask',
                'key' => 'age',
                'op' => '>',
                'value' => 20,
            ],
        ])->assertOk();

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$match->id}/round/action", [
            'action' => [
                'type' => 'guess',
                'player_id' => $secretId,
            ],
        ])->assertOk();

        $this->assertSame('finished', PvpMatch::find($match->id)->status);
    }
}
