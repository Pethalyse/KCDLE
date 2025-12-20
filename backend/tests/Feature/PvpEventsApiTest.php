<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpEventsApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_events_requires_auth(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $this->getJson("/api/pvp/matches/{$match->id}/events?after_id=0")->assertStatus(401);
    }

    public function test_events_forbidden_for_non_participant(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');
        $u3 = User::factory()->create();

        $this->actingAs($u3, 'sanctum')
            ->getJson("/api/pvp/matches/{$match->id}/events?after_id=0")
            ->assertStatus(403);
    }

    public function test_events_default_returns_events_payload(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $res = $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$match->id}/events?after_id=0")
            ->assertOk()
            ->json();

        $this->assertIsArray($res);
        $this->assertArrayNotHasKey('state', $res);
    }

    public function test_events_include_state_wraps_payload(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $secretId = $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$match->id}/round")->assertOk();

        $this->actingAs($u1, 'sanctum')->postJson("/api/pvp/matches/{$match->id}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk();

        $this->actingAs($u2, 'sanctum')->postJson("/api/pvp/matches/{$match->id}/round/action", [
            'action' => ['type' => 'guess', 'player_id' => $secretId],
        ])->assertOk();

        $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$match->id}/events?after_id=0&include_state=1")
            ->assertOk()
            ->assertJsonStructure([
                'events',
                'state' => ['id', 'match_id', 'status', 'best_of', 'current_round'],
            ]);
    }

    public function test_events_invalid_after_id_is_treated_as_zero(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$match->id}/events?after_id=abc")
            ->assertOk();
    }
}
