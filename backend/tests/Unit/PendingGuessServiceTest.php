<?php

namespace Tests\Unit;

use App\Models\DailyGame;
use App\Models\PendingGuess;
use App\Models\User;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use App\Services\AchievementService;
use App\Services\AnonKeyService;
use App\Services\PendingGuessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PendingGuessServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_import_returns_empty_collection_when_no_pending_guesses(): void
    {
        $user = User::factory()->create();

        $anonKeyMock = Mockery::mock(AnonKeyService::class);
        $anonKeyMock->shouldReceive('fromRequest')->once()->andReturn('anon-key');

        $achievementMock = Mockery::mock(AchievementService::class);
        $achievementMock->shouldNotReceive('handleGameWin');

        $service = new PendingGuessService($achievementMock, $anonKeyMock);

        $request = Request::create('/auth/login', 'POST');
        $unlocked = $service->import($user, $request);

        $this->assertCount(0, $unlocked);
        $this->assertSame(0, PendingGuess::query()->count());
        $this->assertSame(0, UserGameResult::query()->count());
        $this->assertSame(0, UserGuess::query()->count());
    }

    public function test_import_groups_by_daily_game_reconstructs_chronological_deduped_sequence_and_persists_user_guesses(): void
    {
        $user = User::factory()->create();

        $daily = DailyGame::create([
            'game' => 'kcdle',
            'player_id' => 999,
            'selected_for_date' => now()->toDateString(),
            'solvers_count' => 0,
            'total_guesses' => 0,
        ]);

        $anonKeyMock = Mockery::mock(AnonKeyService::class);
        $anonKeyMock->shouldReceive('fromRequest')->once()->andReturn('anon-key');

        $achievementMock = Mockery::mock(AchievementService::class);
        $achievementMock->shouldReceive('handleGameWin')
            ->once()
            ->andReturn(collect());

        PendingGuess::create([
            'anon_key' => 'anon-key',
            'daily_game_id' => $daily->id,
            'game' => 'kcdle',
            'player_id' => 111,
            'guess_order' => 2,
            'correct' => false,
            'created_at' => now()->subMinutes(10),
            'updated_at' => now()->subMinutes(10),
        ]);

        PendingGuess::create([
            'anon_key' => 'anon-key',
            'daily_game_id' => $daily->id,
            'game' => 'kcdle',
            'player_id' => 222,
            'guess_order' => 1,
            'correct' => false,
            'created_at' => now()->subMinutes(20),
            'updated_at' => now()->subMinutes(20),
        ]);

        PendingGuess::create([
            'anon_key' => 'anon-key',
            'daily_game_id' => $daily->id,
            'game' => 'kcdle',
            'player_id' => 222,
            'guess_order' => 3,
            'correct' => false,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        PendingGuess::create([
            'anon_key' => 'anon-key',
            'daily_game_id' => $daily->id,
            'game' => 'kcdle',
            'player_id' => 999,
            'guess_order' => 4,
            'correct' => true,
            'created_at' => now()->subMinutes(1),
            'updated_at' => now()->subMinutes(1),
        ]);

        $service = new PendingGuessService($achievementMock, $anonKeyMock);
        $request = Request::create('/auth/login', 'POST');

        $unlocked = $service->import($user, $request);

        $this->assertCount(0, $unlocked);

        $this->assertSame(0, PendingGuess::query()->where('anon_key', 'anon-key')->count(), 'Pending guesses should be deleted after import');

        $result = UserGameResult::query()->where('user_id', $user->id)->where('daily_game_id', $daily->id)->first();
        $this->assertNotNull($result);
        $this->assertNotNull($result->won_at, 'won_at should be set when a correct guess exists');
        $this->assertSame(3, (int) $result->guesses_count, 'guesses_count should be the index of the first correct guess + 1 (after de-duplication)');

        $guesses = UserGuess::query()
            ->where('user_game_result_id', $result->id)
            ->orderBy('guess_order')
            ->get();

        $this->assertCount(3, $guesses, 'Sequence should be de-duplicated by player_id');
        $this->assertSame([222, 111, 999], $guesses->pluck('player_id')->all(), 'Guesses should follow created_at then guess_order ordering after de-duplication');
        $this->assertSame([1, 2, 3], $guesses->pluck('guess_order')->all());
    }

    public function test_import_should_not_trigger_achievements_if_user_already_won_that_daily_game(): void
    {
        $user = User::factory()->create();

        $daily = DailyGame::create([
            'game' => 'kcdle',
            'player_id' => 555,
            'selected_for_date' => now()->toDateString(),
            'solvers_count' => 0,
            'total_guesses' => 0,
        ]);

        $existing = UserGameResult::create([
            'user_id' => $user->id,
            'daily_game_id' => $daily->id,
            'game' => 'kcdle',
            'guesses_count' => 2,
            'won_at' => now()->subHour(),
        ]);

        PendingGuess::create([
            'anon_key' => 'anon-key',
            'daily_game_id' => $daily->id,
            'game' => 'kcdle',
            'player_id' => 555,
            'guess_order' => 1,
            'correct' => true,
        ]);

        $anonKeyMock = Mockery::mock(AnonKeyService::class);
        $anonKeyMock->shouldReceive('fromRequest')->once()->andReturn('anon-key');

        $achievementMock = Mockery::mock(AchievementService::class);
        $achievementMock->shouldNotReceive('handleGameWin');

        $service = new PendingGuessService($achievementMock, $anonKeyMock);
        $request = Request::create('/auth/login', 'POST');

        $service->import($user, $request);

        $existing->refresh();
        $this->assertNotNull($existing->won_at);
        $this->assertSame(2, (int) $existing->guesses_count);
    }

}
