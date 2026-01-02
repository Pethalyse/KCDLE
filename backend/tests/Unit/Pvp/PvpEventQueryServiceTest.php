<?php

namespace Tests\Unit\Pvp;

use App\Models\PvpMatch;
use App\Models\PvpMatchEvent;
use App\Services\Pvp\PvpEventQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PvpEventQueryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_limit_is_clamped_and_cursor_works(): void
    {
        $match = PvpMatch::factory()->create();

        for ($i = 0; $i < 210; $i++) {
            PvpMatchEvent::create([
                'match_id' => $match->id,
                'type' => 'x',
                'payload' => ['i' => $i],
            ]);
        }

        $svc = app(PvpEventQueryService::class);

        $res = $svc->fetch($match->id, 0, 999);

        $this->assertCount(200, $res['events']);

        $after = $res['last_id'];
        $res2 = $svc->fetch($match->id, $after, 200);

        $this->assertCount(10, $res2['events']);
    }
}
