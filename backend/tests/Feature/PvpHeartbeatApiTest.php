<?php

namespace Tests\Feature;

use App\Models\PvpMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpHeartbeatApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_heartbeat_requires_auth(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $this->postJson("/api/pvp/matches/{$match->id}/heartbeat")->assertStatus(401);
    }

    public function test_heartbeat_forbidden_for_non_participant(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');
        $u3 = User::factory()->create();

        $this->actingAs($u3, 'sanctum')
            ->postJson("/api/pvp/matches/{$match->id}/heartbeat")
            ->assertStatus(403);
    }

    public function test_heartbeat_returns_light_payload_by_default(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $res = $this->actingAs($u1, 'sanctum')
            ->postJson("/api/pvp/matches/{$match->id}/heartbeat")
            ->assertOk()
            ->json();

        $this->assertIsArray($res);
        $this->assertArrayNotHasKey('state', $res);
        $this->assertArrayNotHasKey('heartbeat', $res);
    }

    public function test_heartbeat_include_state_wraps_payload(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $this->actingAs($u1, 'sanctum')
            ->postJson("/api/pvp/matches/{$match->id}/heartbeat?include_state=1")
            ->assertOk()
            ->assertJsonStructure([
                'heartbeat',
                'state' => ['id', 'match_id', 'status', 'best_of', 'current_round'],
            ]);
    }

    public function test_heartbeat_does_not_work_on_finished_match(): void
    {
        Config::set('pvp.round_pool', ['classic']);
        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $m = PvpMatch::findOrFail($match->id);
        $m->status = 'finished';
        $m->save();

        $this->actingAs($u1, 'sanctum')
            ->postJson("/api/pvp/matches/{$match->id}/heartbeat")
            ->assertStatus(409);
    }
}
