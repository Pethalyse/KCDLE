<?php

namespace Tests\Feature;

use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Models\PvpQueueEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpQueueApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_join_queue_creates_entry(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic']);

        $u = User::factory()->create();

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'queued');

        $this->assertSame(1, PvpQueueEntry::count());
        $this->assertSame((int) $u->id, (int) PvpQueueEntry::first()->user_id);
        $this->assertSame('kcdle', (string) PvpQueueEntry::first()->game);
    }

    public function test_user_cannot_queue_on_two_different_games(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic']);

        $u = User::factory()->create();

        $this->actingAs($u, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/pvp/games/lecdle/queue/join', ['best_of' => 1])
            ->assertStatus(409);
    }

    public function test_two_users_get_matched_and_queue_is_cleared(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic']);

        $this->pvpSeedMinimalKcdlePlayer();

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $this->actingAs($u1, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();

        $res = $this->actingAs($u2, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk()->json();

        $matchId = (int) ($res['match_id'] ?? 0);
        $this->assertGreaterThan(0, $matchId);

        $this->assertSame(0, PvpQueueEntry::count());
        $this->assertSame(1, PvpMatch::whereKey($matchId)->count());
        $this->assertSame(2, PvpMatchPlayer::where('match_id', $matchId)->count());
    }

    public function test_leave_queue_removes_entry(): void
    {
        Config::set('pvp.allowed_best_of', [1, 3, 5]);
        Config::set('pvp.round_pool', ['classic']);

        $u = User::factory()->create();
        $this->actingAs($u, 'sanctum')->postJson('/api/pvp/games/kcdle/queue/join', ['best_of' => 1])->assertOk();

        $this->actingAs($u, 'sanctum')
            ->postJson('/api/pvp/games/kcdle/queue/leave')
            ->assertOk()
            ->assertJsonPath('status', 'left');

        $this->assertSame(0, PvpQueueEntry::count());
    }
}
