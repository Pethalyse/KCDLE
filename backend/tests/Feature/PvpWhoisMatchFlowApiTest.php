<?php

namespace Tests\Feature;

use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Models\User;
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
                'chooser_rule' => 'random_first_then_last_winner',
                'chooser_user_id' => (int) $u1->id,
                'last_round_winner_user_id' => null,
                'source' => 'queue',
            ],
            'started_at' => now(),
        ]);

        PvpMatchPlayer::create([
            'match_id' => (int) $match->id,
            'user_id' => (int) $u1->id,
            'seat' => 1,
            'points' => 0,
            'last_seen_at' => now(),
            'last_action_at' => now(),
        ]);

        PvpMatchPlayer::create([
            'match_id' => (int) $match->id,
            'user_id' => (int) $u2->id,
            'seat' => 2,
            'points' => 0,
            'last_seen_at' => now(),
            'last_action_at' => now(),
        ]);

        $matchId = (int) $match->id;

        $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$matchId}/round")
            ->assertOk()
            ->assertJsonPath('round_type', 'whois')
            ->assertJsonPath('public.phase', 'whois')
            ->assertJsonPath('public.can_choose_turn', true)
            ->assertJsonPath('public.turn_user_id', null);

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
