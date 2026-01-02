<?php

namespace Tests\Feature;

use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PvpAfkSweepFlowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_afk_player_loses_match(): void
    {
        Carbon::setTestNow(now());

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $match = PvpMatch::create([
            'game' => 'kcdle',
            'status' => 'active',
            'best_of' => 1,
            'current_round' => 1,
            'rounds' => ['classic'],
            'state' => [],
            'started_at' => now()->subMinutes(10),
        ]);

        PvpMatchPlayer::insert([
            [
                'match_id' => $match->id,
                'user_id' => $u1->id,
                'seat' => 1,
                'last_seen_at' => now()->subMinutes(6),
            ],
            [
                'match_id' => $match->id,
                'user_id' => $u2->id,
                'seat' => 2,
                'last_seen_at' => now(),
            ],
        ]);

        $this->artisan('pvp:afk-sweep')->assertExitCode(0);

        $match->refresh();

        $this->assertSame('finished', $match->status);
        $this->assertSame($u2->id, $match->state['winner_user_id']);
        $this->assertSame($u1->id, $match->state['afk_user_id']);
    }
}
