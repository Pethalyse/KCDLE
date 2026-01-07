<?php

namespace Tests\Unit\Pvp;

use App\Models\PvpMatchPlayer;
use App\Models\User;
use App\Services\Pvp\PvpHeartbeatService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpHeartbeatServiceTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_heartbeat_updates_last_seen_at_only(): void
    {
        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $row = PvpMatchPlayer::where('match_id', $match->id)->where('user_id', $u1->id)->firstOrFail();
        $row->last_seen_at = now()->subMinutes(10);
        $row->last_action_at = now()->subMinutes(9);
        $row->save();

        $service = app(PvpHeartbeatService::class);
        $res = $service->heartbeat($match, (int) $u1->id);

        $this->assertIsArray($res);

        $fresh = PvpMatchPlayer::where('match_id', $match->id)->where('user_id', $u1->id)->firstOrFail();

        $lastSeen = $this->toCarbon($fresh->last_seen_at);
        $this->assertNotNull($lastSeen);
        $this->assertTrue($lastSeen->greaterThan(now()->subSeconds(10)));

        $lastAction = $this->toCarbon($fresh->last_action_at);
        $this->assertNotNull($lastAction);
        $this->assertTrue($lastAction->lessThan(now()->subMinutes(1)));
    }

    public function test_heartbeat_for_non_participant_throws(): void
    {
        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        $u3 = User::factory()->create();

        $this->expectException(HttpException::class);
        app(PvpHeartbeatService::class)->heartbeat($match, (int) $u3->id);
    }

    private function toCarbon(mixed $v): ?Carbon
    {
        if ($v === null) {
            return null;
        }
        if ($v instanceof Carbon) {
            return $v;
        }
        if ($v instanceof CarbonInterface) {
            return Carbon::instance($v);
        }
        if ($v instanceof \DateTimeInterface) {
            return Carbon::instance($v);
        }
        if (is_string($v) && $v !== '') {
            return Carbon::parse($v);
        }
        return null;
    }
}
