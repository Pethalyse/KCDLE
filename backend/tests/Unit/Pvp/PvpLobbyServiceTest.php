<?php

namespace Tests\Unit\Pvp;

use App\Models\PvpActiveMatchLock;
use App\Models\PvpLobby;
use App\Models\PvpLobbyEvent;
use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Models\PvpQueueEntry;
use App\Models\User;
use App\Services\Pvp\PvpLobbyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpLobbyServiceTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.default_best_of', 5);
        Config::set('pvp.round_pool', ['classic', 'whois', 'locked_infos', 'draft', 'reveal_race']);
        Config::set('pvp.disable_shuffle', true);
    }

    public function test_create_lobby_persists_and_emits_event_and_clears_queue(): void
    {
        $service = app(PvpLobbyService::class);

        $host = User::factory()->create();

        PvpQueueEntry::create([
            'user_id' => (int) $host->id,
            'game' => 'kcdle',
            'best_of' => 1,
            'created_at' => now(),
        ]);

        $lobby = $service->createLobby($host, 'kcdle', 3);

        $this->assertInstanceOf(PvpLobby::class, $lobby);
        $this->assertSame('open', $lobby->status);
        $this->assertSame('kcdle', $lobby->game);
        $this->assertSame(3, (int) $lobby->best_of);
        $this->assertSame((int) $host->id, (int) $lobby->host_user_id);
        $this->assertNull($lobby->guest_user_id);
        $this->assertNotEmpty($lobby->code);

        $this->assertSame(0, PvpQueueEntry::where('user_id', (int) $host->id)->count());
        $this->assertSame(1, PvpLobbyEvent::where('lobby_id', (int) $lobby->id)->where('type', 'lobby_created')->count());
    }

    public function test_create_lobby_rejects_unknown_game(): void
    {
        $service = app(PvpLobbyService::class);
        $host = User::factory()->create();

        $this->expectExceptionMessage('Unknown game.');
        $service->createLobby($host, 'unknown', 1);
    }

    public function test_create_lobby_rejects_invalid_best_of(): void
    {
        $service = app(PvpLobbyService::class);
        $host = User::factory()->create();

        $this->expectExceptionMessage('Invalid best-of format.');
        $service->createLobby($host, 'kcdle', 2);
    }

    public function test_user_cannot_create_lobby_when_in_active_match(): void
    {
        $service = app(PvpLobbyService::class);

        $this->pvpSeedMinimalKcdlePlayer();

        [$match, $host, $opponent] = $this->pvpCreateMatch('kcdle', 'classic');

        PvpActiveMatchLock::create([
            'user_id' => (int) $host->id,
            'match_id' => (int) $match->id,
            'created_at' => now(),
        ]);

        $this->expectExceptionMessage('You are already in an active match.');
        $service->createLobby($host, 'kcdle', 1);
    }

    public function test_join_lobby_sets_guest_and_emits_event(): void
    {
        $service = app(PvpLobbyService::class);

        $host = User::factory()->create();
        $guest = User::factory()->create();

        $lobby = $service->createLobby($host, 'kcdle', 1);
        $joined = $service->joinLobby($guest, $lobby->code);

        $this->assertSame((int) $lobby->id, (int) $joined->id);
        $this->assertSame((int) $guest->id, (int) $joined->guest_user_id);
        $this->assertSame(1, PvpLobbyEvent::where('lobby_id', (int) $lobby->id)->where('type', 'guest_joined')->count());
    }

    public function test_join_lobby_rejects_when_closed_or_started(): void
    {
        $service = app(PvpLobbyService::class);
        $host = User::factory()->create();
        $guest = User::factory()->create();

        $lobby = $service->createLobby($host, 'kcdle', 1);
        $lobby->status = 'closed';
        $lobby->save();

        $this->expectExceptionMessage('Lobby is not open.');
        $service->joinLobby($guest, $lobby->code);
    }

    public function test_join_lobby_rejects_when_already_has_other_guest(): void
    {
        $service = app(PvpLobbyService::class);
        $host = User::factory()->create();
        $guest1 = User::factory()->create();
        $guest2 = User::factory()->create();

        $lobby = $service->createLobby($host, 'kcdle', 1);
        $service->joinLobby($guest1, $lobby->code);

        $this->expectExceptionMessage('Lobby already has a guest.');
        $service->joinLobby($guest2, $lobby->code);
    }

    public function test_leave_lobby_clears_guest_and_emits_event(): void
    {
        $service = app(PvpLobbyService::class);
        $host = User::factory()->create();
        $guest = User::factory()->create();

        $lobby = $service->createLobby($host, 'kcdle', 1);
        $service->joinLobby($guest, $lobby->code);

        $left = $service->leaveLobby($guest, $lobby);
        $this->assertNull($left->guest_user_id);
        $this->assertSame(1, PvpLobbyEvent::where('lobby_id', (int) $lobby->id)->where('type', 'guest_left')->count());
    }

    public function test_host_cannot_leave_lobby(): void
    {
        $service = app(PvpLobbyService::class);
        $host = User::factory()->create();

        $lobby = $service->createLobby($host, 'kcdle', 1);

        $this->expectExceptionMessage('Host cannot leave. Close the lobby instead.');
        $service->leaveLobby($host, $lobby);
    }

    public function test_close_lobby_requires_host_and_emits_event(): void
    {
        $service = app(PvpLobbyService::class);
        $host = User::factory()->create();
        $guest = User::factory()->create();

        $lobby = $service->createLobby($host, 'kcdle', 1);

        $this->expectExceptionMessage('Not the lobby host.');
        $service->closeLobby($guest, $lobby);
    }

    public function test_start_lobby_creates_match_players_locks_and_updates_lobby(): void
    {
        $service = app(PvpLobbyService::class);
        $this->pvpSeedMinimalKcdlePlayer();

        $host = User::factory()->create();
        $guest = User::factory()->create();

        $lobby = $service->createLobby($host, 'kcdle', 1);
        $service->joinLobby($guest, $lobby->code);

        $result = $service->startLobby($host, $lobby);

        $this->assertArrayHasKey('match_id', $result);
        $this->assertGreaterThan(0, (int) $result['match_id']);

        $lobby = $lobby->fresh();
        $this->assertSame('started', $lobby->status);
        $this->assertSame((int) $result['match_id'], (int) $lobby->match_id);

        $this->assertSame(1, PvpMatch::whereKey((int) $result['match_id'])->count());
        $this->assertSame(2, PvpMatchPlayer::where('match_id', (int) $result['match_id'])->count());
        $this->assertSame(2, PvpActiveMatchLock::where('match_id', (int) $result['match_id'])->count());
        $this->assertGreaterThanOrEqual(2, PvpLobbyEvent::where('lobby_id', (int) $lobby->id)->count());
    }

    public function test_start_lobby_requires_guest(): void
    {
        $service = app(PvpLobbyService::class);
        $this->pvpSeedMinimalKcdlePlayer();

        $host = User::factory()->create();
        $lobby = $service->createLobby($host, 'kcdle', 1);

        $this->expectExceptionMessage('Lobby has no guest.');
        $service->startLobby($host, $lobby);
    }
}
