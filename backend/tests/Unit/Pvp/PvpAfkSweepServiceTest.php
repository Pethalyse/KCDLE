<?php

namespace Tests\Unit\Pvp;

use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Services\Pvp\PvpAfkSweepService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpAfkSweepServiceTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_presence_timeout_forfeits_match(): void
    {
        $this->pvpConfigureForSingleRound('classic');
        Config::set('pvp.presence_seconds', 90);

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'classic');

        PvpMatchPlayer::where('match_id', $match->id)->where('user_id', $u1->id)->update([
            'last_seen_at' => now()->subSeconds(91),
            'last_action_at' => now(),
        ]);

        $res = app(PvpAfkSweepService::class)->sweep();
        $this->assertSame(1, (int) $res['checked']);
        $this->assertSame(1, (int) $res['forfeited']);

        $m = PvpMatch::findOrFail($match->id);
        $this->assertSame('finished', $m->status);
        $this->assertSame('afk', (string) ($m->state['ended_reason'] ?? ''));
        $this->assertSame((int) $u2->id, (int) ($m->state['winner_user_id'] ?? 0));
    }

    public function test_idle_timeout_only_applies_to_turn_user_in_turn_based_rounds(): void
    {
        $this->pvpConfigureForSingleRound('whois');
        Config::set('pvp.idle_seconds', 300);

        [$match, $u1, $u2] = $this->pvpCreateMatch('kcdle', 'whois');

        $match->state = array_replace_recursive((array) $match->state, [
            'turn_user_id' => (int) $u1->id,
            'round_data' => ['whois' => []],
        ]);
        $match->save();

        PvpMatchPlayer::where('match_id', $match->id)->where('user_id', $u2->id)->update([
            'last_seen_at' => now(),
            'last_action_at' => now()->subSeconds(301),
        ]);

        $res = app(PvpAfkSweepService::class)->sweep();
        $this->assertSame(1, (int) $res['checked']);
        $this->assertSame(0, (int) $res['forfeited']);

        PvpMatchPlayer::where('match_id', $match->id)->where('user_id', $u1->id)->update([
            'last_seen_at' => now(),
            'last_action_at' => now()->subSeconds(301),
        ]);

        $res2 = app(PvpAfkSweepService::class)->sweep();
        $this->assertSame(1, (int) $res2['forfeited']);

        $m = PvpMatch::findOrFail($match->id);
        $this->assertSame('finished', $m->status);
        $this->assertSame((int) $u2->id, (int) ($m->state['winner_user_id'] ?? 0));
    }
}
