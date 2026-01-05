<?php

namespace Feature\Pvp;

use App\Models\PvpActiveMatchLock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

class PvpResumeApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    public function test_resume_none_when_user_idle(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u, 'sanctum')
            ->getJson('/api/pvp/resume')
            ->assertOk()
            ->assertJson(['status' => 'none']);
    }

    public function test_resume_in_match_when_lock_exists(): void
    {
        [$match, $u1] = $this->pvpCreateMatch('kcdle', 'classic', 1);

        PvpActiveMatchLock::create([
            'user_id' => (int) $u1->id,
            'match_id' => (int) $match->id,
        ]);

        $this->actingAs($u1, 'sanctum')
            ->getJson('/api/pvp/resume')
            ->assertOk()
            ->assertJson([
                'status' => 'in_match',
                'match_id' => (int) $match->id,
            ]);
    }
}
