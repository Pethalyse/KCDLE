<?php

namespace Tests\Feature;

use App\Models\PvpMatchEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpEventsPaginationApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_events_limit_is_clamped_to_200_and_after_id_cursor_works(): void
    {
        [$match, $u1] = $this->pvpCreateMatch('kcdle', 'classic', 1);

        for ($i = 0; $i < 250; $i++) {
            PvpMatchEvent::create([
                'match_id' => $match->id,
                'user_id' => null,
                'type' => 'test_event',
                'payload' => ['i' => $i],
                'created_at' => now(),
            ]);
        }

        $resp = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$match->id}/events?after_id=0&limit=500")->assertOk()->json();
        $events = (array) ($resp['events'] ?? []);

        $this->assertCount(200, $events);

        $lastId = (int) ($resp['last_id'] ?? 0);
        $this->assertSame((int) ($events[199]['id'] ?? 0), $lastId);

        $resp2 = $this->actingAs($u1, 'sanctum')->getJson("/api/pvp/matches/{$match->id}/events?after_id={$lastId}&limit=200")->assertOk()->json();
        $events2 = (array) ($resp2['events'] ?? []);

        $this->assertCount(50, $events2);
        $this->assertGreaterThan($lastId, (int) ($events2[0]['id'] ?? 0));
    }
}
