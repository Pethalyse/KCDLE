<?php

namespace Feature\Pvp;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpAfkSweepFlowApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_afk_player_loses_match(): void
    {
        Carbon::setTestNow(now());

        Config::set('pvp.afk_seconds', 90);

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic', 1);

        $match->started_at = now()->subMinutes(10);
        $match->save();

        $match->players()->where('user_id', $u1->id)->update([
            'last_seen_at' => now()->subMinutes(6),
        ]);

        $match->players()->where('user_id', $u2->id)->update([
            'last_seen_at' => now(),
        ]);

        $this->artisan('pvp:afk-sweep')->assertExitCode(0);

        $match->refresh();

        $this->assertSame('finished', $match->status);
        $this->assertSame((int) $u2->id, (int) ($match->state['winner_user_id'] ?? 0));
        $this->assertSame('afk', (string) ($match->state['ended_reason'] ?? ''));
        $this->assertSame((int) $u1->id, (int) ($match->state['forfeiting_user_id'] ?? 0));

        Carbon::setTestNow();
    }
}
