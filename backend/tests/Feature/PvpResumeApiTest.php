<?php

namespace Tests\Feature;

use App\Models\PvpActiveMatchLock;
use App\Models\PvpMatch;
use App\Models\PvpMatchPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PvpResumeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_resume_none_when_user_idle(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u, 'sanctum')
            ->getJson('/api/pvp/resume')
            ->assertOk()
            ->assertJson(['state' => 'none']);
    }

    public function test_resume_in_match_when_lock_exists(): void
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        $match = PvpMatch::create([
            'game' => 'kcdle',
            'status' => 'active',
            'best_of' => 1,
            'current_round' => 1,
            'rounds' => ['classic'],
            'state' => ['round' => 1],
            'started_at' => now(),
        ]);

        PvpMatchPlayer::create([
            'match_id' => $match->id,
            'user_id' => $u1->id,
            'seat' => 1,
        ]);

        PvpMatchPlayer::create([
            'match_id' => $match->id,
            'user_id' => $u2->id,
            'seat' => 2,
        ]);

        PvpActiveMatchLock::create([
            'user_id' => $u1->id,
            'match_id' => $match->id,
        ]);

        $this->actingAs($u1, 'sanctum')
            ->getJson('/api/pvp/resume')
            ->assertOk()
            ->assertJson([
                'state' => 'in_match',
                'match_id' => $match->id,
            ]);
    }
}
