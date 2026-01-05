<?php

namespace Feature\Pvp\Events;

use App\Models\PvpMatchEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpEventsPaginationApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_events_limit_is_clamped_to_200(): void
    {
        $this->pvpSeedMinimalKcdlePlayer('only', 'Only');

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic', 1);

        for ($i = 0; $i < 260; $i++) {
            PvpMatchEvent::create([
                'match_id' => $match->id,
                'user_id' => null,
                'type' => 'test_event',
                'payload' => ['i' => $i],
            ]);
        }

        $resp = $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$match->id}/events?after_id=0&limit=500")
            ->assertOk()
            ->json();

        $events = (array) ($resp['events'] ?? []);
        $this->assertCount(200, $events);
    }
}
