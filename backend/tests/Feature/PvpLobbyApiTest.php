<?php

namespace Tests\Feature;

use App\Models\PvpLobby;
use App\Models\PvpLobbyEvent;
use App\Models\PvpMatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpLobbyApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.default_best_of', 5);
        Config::set('pvp.round_pool', ['classic']);
        Config::set('pvp.disable_shuffle', true);
        $this->pvpSeedMinimalKcdlePlayer();
    }

    public function test_create_lobby_requires_auth(): void
    {
        $this->postJson('/api/pvp/lobbies', ['game' => 'kcdle', 'best_of' => 1])->assertStatus(401);
    }

    public function test_create_and_me_and_show_and_peek(): void
    {
        $u1 = User::factory()->create();

        $json = $this->actingAs($u1, 'sanctum')
            ->postJson('/api/pvp/lobbies', ['game' => 'kcdle', 'best_of' => 3])
            ->assertOk()
            ->json();

        $code = (string) ($json['code'] ?? '');
        $this->assertNotSame('', $code);

        $this->actingAs($u1, 'sanctum')
            ->getJson('/api/pvp/lobbies/me')
            ->assertOk()
            ->assertJsonPath('status', 'in_lobby')
            ->assertJsonPath('lobby.code', $code)
            ->assertJsonPath('lobby.is_host', true);

        $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/lobbies/code/{$code}")
            ->assertOk()
            ->assertJsonPath('code', $code)
            ->assertJsonPath('host.id', (int) $u1->id)
            ->assertJsonPath('guest', null);

        $this->getJson("/api/pvp/lobbies/code/{$code}/peek")
            ->assertOk()
            ->assertJsonPath('code', $code)
            ->assertJsonPath('host.name', (string) $u1->name);
    }

    public function test_join_leave_close_events_and_start(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();

        $created = $this->actingAs($host, 'sanctum')
            ->postJson('/api/pvp/lobbies', ['game' => 'kcdle', 'best_of' => 1])
            ->assertOk()
            ->json();

        $code = (string) ($created['code'] ?? '');
        $this->assertNotSame('', $code);

        $joined = $this->actingAs($guest, 'sanctum')
            ->postJson("/api/pvp/lobbies/code/{$code}/join")
            ->assertOk()
            ->assertJsonPath('code', $code)
            ->json();

        $lobbyId = (int) ($joined['id'] ?? 0);
        $this->assertGreaterThan(0, $lobbyId);

        $this->actingAs($host, 'sanctum')
            ->getJson("/api/pvp/lobbies/{$lobbyId}")
            ->assertOk()
            ->assertJsonPath('guest.id', (int) $guest->id)
            ->assertJsonPath('is_host', true);

        $this->actingAs($guest, 'sanctum')
            ->getJson("/api/pvp/lobbies/{$lobbyId}")
            ->assertOk()
            ->assertJsonPath('guest.id', (int) $guest->id)
            ->assertJsonPath('is_host', false);

        $events = $this->actingAs($host, 'sanctum')
            ->getJson("/api/pvp/lobbies/{$lobbyId}/events?after_id=0&limit=200")
            ->assertOk()
            ->json('events');

        $this->assertIsArray($events);
        $this->assertNotEmpty($events);

        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/pvp/lobbies/{$lobbyId}/leave")
            ->assertOk();

        $this->assertSame(1, PvpLobbyEvent::where('lobby_id', $lobbyId)->where('type', 'guest_left')->count());

        $this->actingAs($guest, 'sanctum')
            ->postJson("/api/pvp/lobbies/code/{$code}/join")
            ->assertOk();

        $started = $this->actingAs($host, 'sanctum')
            ->postJson("/api/pvp/lobbies/{$lobbyId}/start")
            ->assertOk()
            ->json();

        $matchId = (int) ($started['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);
        $this->assertSame(1, PvpMatch::whereKey($matchId)->count());

        $lobby = PvpLobby::findOrFail($lobbyId);
        $this->assertSame('started', (string) $lobby->status);
        $this->assertSame($matchId, (int) $lobby->match_id);

        $this->getJson("/api/pvp/lobbies/code/{$code}/peek")->assertStatus(404);
    }

    public function test_non_participant_cannot_view_lobby_or_events_or_close(): void
    {
        $host = User::factory()->create();
        $intruder = User::factory()->create();

        $created = $this->actingAs($host, 'sanctum')
            ->postJson('/api/pvp/lobbies', ['game' => 'kcdle', 'best_of' => 1])
            ->assertOk()
            ->json();

        $lobbyId = (int) ($created['id'] ?? 0);
        $this->assertGreaterThan(0, $lobbyId);

        $this->actingAs($intruder, 'sanctum')->getJson("/api/pvp/lobbies/{$lobbyId}")->assertStatus(403);
        $this->actingAs($intruder, 'sanctum')->getJson("/api/pvp/lobbies/{$lobbyId}/events")->assertStatus(403);
        $this->actingAs($intruder, 'sanctum')->postJson("/api/pvp/lobbies/{$lobbyId}/close")->assertStatus(403);
    }
}
