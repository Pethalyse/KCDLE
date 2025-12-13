<?php

namespace Tests\Unit;

use App\Models\DailyGame;
use App\Models\FriendGroup;
use App\Models\User;
use App\Models\UserGameResult;
use App\Services\UserLeaderboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class UserLeaderboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_global_leaderboard_returns_empty_paginator_when_no_wins(): void
    {
        $this->app->instance('request', Request::create('/api/leaderboard', 'GET'));

        $service = $this->app->make(UserLeaderboardService::class);
        $paginator = $service->getGlobalLeaderboard('kcdle', 50, 1);

        $this->assertSame(0, $paginator->total());
        $this->assertCount(0, $paginator->items());
    }

    public function test_get_global_leaderboard_orders_users_by_final_score_then_wins_then_average_guesses_then_user_id(): void
    {
        $this->app->instance('request', Request::create('/api/leaderboard', 'GET'));

        $u1 = User::factory()->create(['name' => 'U1']);
        $u2 = User::factory()->create(['name' => 'U2']);
        $u3 = User::factory()->create(['name' => 'U3']);

        $this->seedWins($u1, 'kcdle', 10, 2);
        $this->seedWins($u2, 'kcdle', 2, 1, 100);
        $this->seedWins($u3, 'kcdle', 2, 3, 200);

        $service = $this->app->make(UserLeaderboardService::class);
        $paginator = $service->getGlobalLeaderboard('kcdle', 50, 1);
        $items = collect($paginator->items());

        $this->assertSame([$u1->id, $u2->id, $u3->id], $items->pluck('user_id')->all());

        $first = $items->first();
        $this->assertSame($u1->id, $first['user_id']);
        $this->assertSame(10, $first['wins']);
        $this->assertSame(2.00, $first['average_guesses']);
        $this->assertNotNull($first['user']);
        $this->assertSame($u1->id, $first['user']['id']);
    }

    public function test_get_group_leaderboard_returns_empty_paginator_when_group_has_no_members(): void
    {
        $this->app->instance('request', Request::create('/api/groups/1/leaderboard', 'GET'));

        $owner = User::factory()->create();
        $group = FriendGroup::create([
            'owner_id' => $owner->id,
            'name' => 'Empty group',
            'slug' => 'empty-group',
            'join_code' => 'ABC123',
        ]);

        $service = $this->app->make(UserLeaderboardService::class);
        $paginator = $service->getGroupLeaderboard('kcdle', $group, 50, 1);

        $this->assertSame(0, $paginator->total());
        $this->assertCount(0, $paginator->items());
    }

    public function test_get_group_leaderboard_only_includes_group_members(): void
    {
        $this->app->instance('request', Request::create('/api/groups/1/leaderboard', 'GET'));

        $owner = User::factory()->create(['name' => 'Owner']);
        $member = User::factory()->create(['name' => 'Member']);
        $outsider = User::factory()->create(['name' => 'Outsider']);

        $group = FriendGroup::create([
            'owner_id' => $owner->id,
            'name' => 'My group',
            'slug' => 'my-group',
            'join_code' => 'ABC123',
        ]);

        $group->users()->attach($owner->id, ['role' => 'owner']);
        $group->users()->attach($member->id, ['role' => 'member']);

        $this->seedWins($owner, 'kcdle', 3, 2);
        $this->seedWins($member, 'kcdle', 2, 2, 50);
        $this->seedWins($outsider, 'kcdle', 50, 1, 500);

        $service = $this->app->make(UserLeaderboardService::class);
        $paginator = $service->getGroupLeaderboard('kcdle', $group, 50, 1);
        $items = collect($paginator->items());

        $this->assertTrue($items->pluck('user_id')->contains($owner->id));
        $this->assertTrue($items->pluck('user_id')->contains($member->id));
        $this->assertFalse($items->pluck('user_id')->contains($outsider->id));
    }

    private function seedWins(User $user, string $game, int $count, int $guessesCount, int $offsetDays = 0): void
    {
        for ($i = 0; $i < $count; $i++) {
            $daily = DailyGame::create([
                'game' => $game,
                'player_id' => 1,
                'selected_for_date' => now()->copy()->subDays(500)->addDays($offsetDays + $i)->toDateString(),
                'solvers_count' => 0,
                'total_guesses' => 0,
            ]);

            UserGameResult::create([
                'user_id' => $user->id,
                'daily_game_id' => $daily->id,
                'game' => $game,
                'guesses_count' => $guessesCount,
                'won_at' => now(),
            ]);
        }
    }
}
