<?php

namespace Tests\Unit;

use App\Models\Achievement;
use App\Models\DailyGame;
use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\Player;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use App\Services\AchievementService;
use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AchievementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected AchievementService $service;

    /**
     * @throws BindingResolutionException
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(AchievementService::class);
    }

    protected function createUser(): User
    {
        return User::factory()->create();
    }

    protected function createGame(string $code): Game
    {
        return Game::firstOrCreate([
            'code' => $code,
            'name' => strtoupper($code),
            'icon_slug' => null,
        ]);
    }

    protected function createPlayer(string $slug): Player
    {
        return Player::firstOrCreate([
            'slug' => $slug,
            'display_name' => $slug,
            'country_code' => null,
            'birthdate' => null,
            'role_id' => null,
        ]);
    }

    protected function createKcdlePlayer(Game $game, Player $player): KcdlePlayer
    {
        return KcdlePlayer::firstOrCreate([
            'player_id' => $player->id,
            'game_id' => $game->id,
            'current_team_id' => null,
            'previous_team_before_kc_id' => null,
            'first_official_year' => 2020,
            'trophies_count' => 0,
            'active' => true,
        ]);
    }

    protected function createDailyGame(string $game, Carbon $date, ?int $playerId = null): DailyGame
    {
        return DailyGame::firstOrCreate([
            'game' => $game,
            'player_id' => $playerId ?? 1,
            'selected_for_date' => $date->copy()->startOfDay(),
            'solvers_count' => 0,
            'total_guesses' => 0,
        ]);
    }

    protected function createWin(User $user, string $game, Carbon $date, int $guessesCount = 1, ?int $dailyGameId = null, ?Carbon $wonAt = null): UserGameResult
    {
        $daily = $dailyGameId
            ? DailyGame::findOrFail($dailyGameId)
            : $this->createDailyGame($game, $date);
        return UserGameResult::create([
            'user_id' => $user->id,
            'daily_game_id' => $daily->id,
            'game' => $game,
            'guesses_count' => $guessesCount,
            'won_at' => ($wonAt ?? $date->copy()->setTime(12, 0, 0)),
        ]);
    }

    protected function assertUnlocked(Collection $unlocked, string $key): void
    {
        $this->assertTrue($unlocked->contains(fn (Achievement $a) => $a->key === $key));
    }

    protected function assertNotUnlocked(Collection $unlocked, string $key): void
    {
        $this->assertFalse($unlocked->contains(fn (Achievement $a) => $a->key === $key));
    }

    public function test_first_win_any_is_unlocked_on_first_global_win(): void
    {
        $user = $this->createUser();
        $date = Carbon::today();
        $this->createDailyGame('kcdle', $date);
        $result = $this->createWin($user, 'kcdle', $date, 3);
        $unlocked = $this->service->handleGameWin($user, $result);
        $this->assertUnlocked($unlocked, 'first_win_any');
        $achievement = Achievement::where('key', 'first_win_any')->firstOrFail();
        $this->assertDatabaseHas('user_achievements', [
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);
    }

    public function test_first_win_any_is_not_unlocked_twice(): void
    {
        $user = $this->createUser();
        $date1 = Carbon::today();
        $date2 = Carbon::today()->addDay();
        $this->createDailyGame('kcdle', $date1);
        $this->createDailyGame('kcdle', $date2);
        $result1 = $this->createWin($user, 'kcdle', $date1, 3);
        $result2 = $this->createWin($user, 'kcdle', $date2, 2);
        $unlocked1 = $this->service->handleGameWin($user, $result1);
        $this->assertUnlocked($unlocked1, 'first_win_any');
        $unlocked2 = $this->service->handleGameWin($user, $result2);
        $this->assertNotUnlocked($unlocked2, 'first_win_any');
        $achievement = Achievement::where('key', 'first_win_any')->firstOrFail();
        $this->assertSame(1, UserAchievement::where('user_id', $user->id)->where('achievement_id', $achievement->id)->count());
    }

    public function test_first_win_game_3_requires_one_win_on_each_dle(): void
    {
        $user = $this->createUser();
        $date = Carbon::create(2025, 1, 1);
        $this->createDailyGame('kcdle', $date);
        $this->createDailyGame('lecdle', $date->copy()->addDay());
        $this->createDailyGame('lfldle', $date->copy()->addDays(2));
        $resultK = $this->createWin($user, 'kcdle', $date, 2);
        $unlocked1 = $this->service->handleGameWin($user, $resultK);
        $this->assertNotUnlocked($unlocked1, 'first_win_game_3');
        $resultLec = $this->createWin($user, 'lecdle', $date->copy()->addDay(), 2);
        $unlocked2 = $this->service->handleGameWin($user, $resultLec);
        $this->assertNotUnlocked($unlocked2, 'first_win_game_3');
        $resultLfl = $this->createWin($user, 'lfldle', $date->copy()->addDays(2), 2);
        $unlocked3 = $this->service->handleGameWin($user, $resultLfl);
        $this->assertUnlocked($unlocked3, 'first_win_game_3');
    }

    public function test_ten_wins_game_requires_ten_wins_on_same_game(): void
    {
        $user = $this->createUser();
        $start = Carbon::create(2025, 1, 1);
        for ($i = 0; $i < 9; $i++) {
            $date = $start->copy()->addDays($i);
            $this->createDailyGame('kcdle', $date);
            $result = $this->createWin($user, 'kcdle', $date, 3);
            $this->service->handleGameWin($user, $result);
        }
        $otherDate = $start->copy()->addDays(9);
        $this->createDailyGame('lecdle', $otherDate);
        $other = $this->createWin($user, 'lecdle', $otherDate, 3);
        $unlockedWrong = $this->service->handleGameWin($user, $other);
        $this->assertNotUnlocked($unlockedWrong, 'ten_wins_game');
        $date10 = $start->copy()->addDays(10);
        $this->createDailyGame('kcdle', $date10);
        $result10 = $this->createWin($user, 'kcdle', $date10, 2);
        $unlocked = $this->service->handleGameWin($user, $result10);
        $this->assertUnlocked($unlocked, 'ten_wins_game');
    }

    public function test_streak_5_game_is_unlocked_when_current_streak_at_least_five(): void
    {
        $user = $this->createUser();
        $start = Carbon::today()->subDays(4);
        for ($i = 0; $i < 4; $i++) {
            $date = $start->copy()->addDays($i);
            $this->createDailyGame('kcdle', $date);
            $result = $this->createWin($user, 'kcdle', $date, 3);
            $this->service->handleGameWin($user, $result);
        }

        $otherDate = $start->copy()->addDays(4);
        $this->createDailyGame('lecdle', $otherDate);
        $other = $this->createWin($user, 'lecdle', $otherDate, 3);
        $unlockedWrong = $this->service->handleGameWin($user, $other);
        $this->assertNotUnlocked($unlockedWrong, 'streak_5_game');

        $date5 = $start->copy()->addDays(5);
        $this->createDailyGame('kcdle', $date5);
        $result5 = $this->createWin($user, 'kcdle', $date5, 3);
        $unlocked = $this->service->handleGameWin($user, $result5);
        $this->assertNotUnlocked($unlocked, 'streak_5_game');

        $date4 = $start->copy()->addDays(4);
        $this->createDailyGame('kcdle', $date4);
        $result4 = $this->createWin($user, 'kcdle', $date4, 3);
        $unlocked = $this->service->handleGameWin($user, $result4);
        $this->assertUnlocked($unlocked, 'streak_5_game');
    }

    public function test_streak_14_game_is_unlocked_when_current_streak_at_least_fourteen(): void
    {
        $user = $this->createUser();
        $start = Carbon::today()->subDays(13);
        for ($i = 0; $i < 13; $i++) {
            $date = $start->copy()->addDays($i);
            $this->createDailyGame('kcdle', $date);
            $result = $this->createWin($user, 'kcdle', $date, 3);
            $this->service->handleGameWin($user, $result);
        }

        $otherDate = $start->copy()->addDays(13);
        $this->createDailyGame('lecdle', $otherDate);
        $other = $this->createWin($user, 'lecdle', $otherDate, 3);
        $unlockedWrong = $this->service->handleGameWin($user, $other);
        $this->assertNotUnlocked($unlockedWrong, 'streak_14_game');

        $date14 = $start->copy()->addDays(14);
        $this->createDailyGame('kcdle', $date14);
        $result14 = $this->createWin($user, 'kcdle', $date14, 3);
        $unlocked = $this->service->handleGameWin($user, $result14);
        $this->assertNotUnlocked($unlocked, 'streak_14_game');

        $date13 = $start->copy()->addDays(13);
        $this->createDailyGame('kcdle', $date13);
        $result13 = $this->createWin($user, 'kcdle', $date13, 3);
        $unlocked = $this->service->handleGameWin($user, $result13);
        $this->assertUnlocked($unlocked, 'streak_14_game');
    }

    public function test_perfect_day_unlocked_when_single_guess_win(): void
    {
        $user = $this->createUser();
        $date = Carbon::create(2025, 2, 1);
        $this->createDailyGame('kcdle', $date);
        $result = $this->createWin($user, 'kcdle', $date, 1);
        $unlocked = $this->service->handleGameWin($user, $result);
        $this->assertUnlocked($unlocked, 'perfect_day');
    }

    public function test_perfect_day_not_unlocked_when_more_than_one_guess(): void
    {
        $user = $this->createUser();
        $date = Carbon::create(2025, 2, 2);
        $this->createDailyGame('kcdle', $date);
        $result = $this->createWin($user, 'kcdle', $date, 2);
        $unlocked = $this->service->handleGameWin($user, $result);
        $this->assertNotUnlocked($unlocked, 'perfect_day');
    }

    public function test_panic_clicker_unlocked_when_win_with_at_least_fifteen_guesses(): void
    {
        $user = $this->createUser();
        $date = Carbon::create(2025, 3, 1);
        $this->createDailyGame('kcdle', $date);
        $result = $this->createWin($user, 'kcdle', $date, 15);
        $unlocked = $this->service->handleGameWin($user, $result);
        $this->assertUnlocked($unlocked, 'panic_clicker');
    }

    public function test_full_roster_tour_unlocked_when_all_kcdle_players_are_tried(): void
    {
        $user = $this->createUser();
        $game = $this->createGame('kcdle');
        $players = [];
        for ($i = 1; $i <= 3; $i++) {
            $players[] = $this->createKcdlePlayer($game, $this->createPlayer('player-' . $i));
        }
        $date = Carbon::create(2025, 4, 1);
        $daily = $this->createDailyGame('kcdle', $date, $players[0]->id);
        $result = UserGameResult::create([
            'user_id' => $user->id,
            'daily_game_id' => $daily->id,
            'game' => 'kcdle',
            'guesses_count' => 4,
            'won_at' => $date->copy()->setTime(13, 0, 0),
        ]);
        foreach ($players as $index => $player) {
            UserGuess::create([
                'user_game_result_id' => $result->id,
                'guess_order' => $index + 1,
                'player_id' => $player->id,
            ]);
        }
        $unlocked = $this->service->handleGameWin($user, $result);
        $this->assertUnlocked($unlocked, 'full_roster_tour');
    }

    public function test_blue_chants_unlocked_after_thirty_wins_on_same_game(): void
    {
        $user = $this->createUser();
        $start = Carbon::create(2025, 5, 1);
        for ($i = 0; $i < 29; $i++) {
            $date = $start->copy()->addDays($i);
            $this->createDailyGame('lecdle', $date);
            $result = $this->createWin($user, 'lecdle', $date, 3);
            $this->service->handleGameWin($user, $result);
        }
        $otherDate = $start->copy()->addDays(29);
        $this->createDailyGame('lfldle', $otherDate);
        $resultOther = $this->createWin($user, 'lfldle', $otherDate, 3);
        $unlocked = $this->service->handleGameWin($user, $resultOther);
        $this->assertNotUnlocked($unlocked, 'blue_chants');
        $date30 = $start->copy()->addDays(29);
        $this->createDailyGame('lecdle', $date30);
        $result30 = $this->createWin($user, 'lecdle', $date30, 3);
        $unlocked = $this->service->handleGameWin($user, $result30);
        $this->assertUnlocked($unlocked, 'blue_chants');
    }

    public function test_graphique_vert_unlocked_after_hundred_wins_all_games_combined(): void
    {
        $user = $this->createUser();
        $start = Carbon::create(2025, 6, 1);
        for ($i = 0; $i < 99; $i++) {
            $date = $start->copy()->addDays($i);
            $game = $i % 2 === 0 ? 'kcdle' : 'lecdle';
            $this->createDailyGame($game, $date);
            $result = $this->createWin($user, $game, $date, 3);
            $this->service->handleGameWin($user, $result);
        }
        $date100 = $start->copy()->addDays(99);
        $this->createDailyGame('lfldle', $date100);
        $result100 = $this->createWin($user, 'kcdle', $date100, 3);
        $unlocked = $this->service->handleGameWin($user, $result100);
        $this->assertUnlocked($unlocked, 'graphique_vert');
    }

    public function test_tacticien_galactique_unlocked_after_fifty_wins_with_average_guesses_at_most_three(): void
    {
        $user = $this->createUser();
        $start = Carbon::create(2025, 7, 1);
        for ($i = 0; $i < 49; $i++) {
            $date = $start->copy()->addDays($i);
            $this->createDailyGame('kcdle', $date);
            $result = $this->createWin($user, 'kcdle', $date, 3);
            $this->service->handleGameWin($user, $result);
        }
        $date50 = $start->copy()->addDays(49);
        $this->createDailyGame('lecdle', $date50);
        $result50 = $this->createWin($user, 'lecdle', $date50, 2);
        $unlocked = $this->service->handleGameWin($user, $result50);
        $this->assertNotUnlocked($unlocked, 'tacticien_galactique');

        $date50 = $start->copy()->addDays(49);
        $this->createDailyGame('kcdle', $date50);
        $result50 = $this->createWin($user, 'kcdle', $date50, 2);
        $unlocked = $this->service->handleGameWin($user, $result50);
        $this->assertUnlocked($unlocked, 'tacticien_galactique');
    }

    public function test_buzzer_beater_unlocked_when_win_happens_in_last_minute_before_reset(): void
    {
        $user = $this->createUser();
        $date = Carbon::create(2025, 8, 1);
        $daily = $this->createDailyGame('kcdle', $date);
        $wonAt = $date->copy()->endOfDay()->subSeconds(30);
        $result = UserGameResult::create([
            'user_id' => $user->id,
            'daily_game_id' => $daily->id,
            'game' => 'kcdle',
            'guesses_count' => 3,
            'won_at' => $wonAt,
        ]);
        $unlocked = $this->service->handleGameWin($user, $result);
        $this->assertUnlocked($unlocked, 'buzzer_beater');
    }

    public function test_buzzer_beater_not_unlocked_when_win_not_happens_in_last_minute_before_reset(): void
    {
        $user = $this->createUser();
        $date = Carbon::create(2025, 8, 1);
        $daily = $this->createDailyGame('kcdle', $date);
        $wonAt = $date->copy()->endOfDay()->subSeconds(61);
        $result = UserGameResult::create([
            'user_id' => $user->id,
            'daily_game_id' => $daily->id,
            'game' => 'kcdle',
            'guesses_count' => 3,
            'won_at' => $wonAt,
        ]);
        $unlocked = $this->service->handleGameWin($user, $result);
        $this->assertNotUnlocked($unlocked, 'buzzer_beater');
    }

    public function test_back_to_back_magic_unlocked_for_two_consecutive_one_guess_wins(): void
    {
        $user = $this->createUser();
        $day1 = Carbon::create(2025, 9, 1);
        $day2 = $day1->copy()->addDay();
        $daily1 = $this->createDailyGame('kcdle', $day1);
        $result1 = UserGameResult::create([
            'user_id' => $user->id,
            'daily_game_id' => $daily1->id,
            'game' => 'kcdle',
            'guesses_count' => 1,
            'won_at' => $day1->copy()->setTime(12, 0, 0),
        ]);
        $this->service->handleGameWin($user, $result1);
        $daily2 = $this->createDailyGame('kcdle', $day2);
        $result2 = UserGameResult::create([
            'user_id' => $user->id,
            'daily_game_id' => $daily2->id,
            'game' => 'kcdle',
            'guesses_count' => 1,
            'won_at' => $day2->copy()->setTime(12, 0, 0),
        ]);
        $unlocked = $this->service->handleGameWin($user, $result2);
        $this->assertUnlocked($unlocked, 'back_to_back_magic');
    }

    public function test_back_to_back_magic_not_unlocked_for_not_two_consecutive_one_guess_wins(): void
    {
        $user = $this->createUser();
        $day1 = Carbon::create(2025, 9, 1);
        $day2 = $day1->copy()->addDays(2);
        $daily1 = $this->createDailyGame('kcdle', $day1);
        $result1 = UserGameResult::create([
            'user_id' => $user->id,
            'daily_game_id' => $daily1->id,
            'game' => 'kcdle',
            'guesses_count' => 1,
            'won_at' => $day1->copy()->setTime(12, 0, 0),
        ]);
        $this->service->handleGameWin($user, $result1);
        $daily2 = $this->createDailyGame('kcdle', $day2);
        $result2 = UserGameResult::create([
            'user_id' => $user->id,
            'daily_game_id' => $daily2->id,
            'game' => 'kcdle',
            'guesses_count' => 1,
            'won_at' => $day2->copy()->setTime(12, 0, 0),
        ]);
        $unlocked = $this->service->handleGameWin($user, $result2);
        $this->assertNotUnlocked($unlocked, 'back_to_back_magic');
    }

    public function test_five_times_under_two_guesses_unlocked_after_five_consecutive_fast_wins(): void
    {
        $user = $this->createUser();
        $start = Carbon::create(2025, 10, 1);
        for ($i = 0; $i < 4; $i++) {
            $date = $start->copy()->addDays($i);
            $this->createDailyGame('kcdle', $date);
            $result = $this->createWin($user, 'kcdle', $date, 2);
            $this->service->handleGameWin($user, $result);
        }
        $date5 = $start->copy()->addDays(4);
        $this->createDailyGame('kcdle', $date5);
        $result5 = $this->createWin($user, 'kcdle', $date5, 2);
        $unlocked = $this->service->handleGameWin($user, $result5);
        $this->assertUnlocked($unlocked, '5_times_under_2_guesses');
    }

    public function test_mois_de_folie_unlocked_when_all_daily_games_of_month_are_won_for_game(): void
    {
        $user = $this->createUser();
        $gameCode = 'kcdle';
        $start = Carbon::create(2025, 11, 1);
        $days = [];
        $i = 0;
        while ($start->copy()->addDays($i)->month === $start->month) {
            $days[] = $this->createDailyGame($gameCode, $start->copy()->addDays($i));
            $i++;
        }
        foreach (array_slice($days, 0, sizeof($days)-1) as $daily) {
            $result = $this->createWin($user, $gameCode, Carbon::parse($daily->selected_for_date), 3, $daily->id);
            $unlocked = $this->service->handleGameWin($user, $result);
            $this->assertNotUnlocked($unlocked, 'mois_de_folie');
        }
        $lastDaily = $days[sizeof($days)-1];
        $lastResult = $this->createWin($user, $gameCode, Carbon::parse($lastDaily->selected_for_date), 3, $lastDaily->id);
        $unlocked = $this->service->handleGameWin($user, $lastResult);
        $this->assertUnlocked($unlocked, 'mois_de_folie');
    }

    public function test_pluie_de_perticoins_unlocked_for_ten_day_cross_game_win_streak(): void
    {
        $user = $this->createUser();
        $start = Carbon::create(2025, 12, 1);
        for ($i = 0; $i < 9; $i++) {
            $date = $start->copy()->addDays($i);
            $game = $i % 2 === 0 ? 'kcdle' : 'lecdle';
            $this->createDailyGame($game, $date);
            $result = $this->createWin($user, $game, $date, 3);
            $this->service->handleGameWin($user, $result);
        }
        $date10 = $start->copy()->addDays(9);
        $this->createDailyGame('lfldle', $date10);
        $result10 = $this->createWin($user, 'lfldle', $date10, 3);
        $unlocked = $this->service->handleGameWin($user, $result10);
        $this->assertUnlocked($unlocked, 'pluie_de_perticoins');
    }

    public function test_club_legend_requires_hundreds_of_wins_long_streak_and_low_average(): void
    {
        $user = $this->createUser();
        $start = Carbon::today()->subDays(364);
        for ($i = 0; $i < 364; $i++) {
            $date = $start->copy()->addDays($i);
            $game = 'kcdle';
            $this->createDailyGame($game, $date);
            $guesses = $i < 30 ? 2 : 3;
            $result = $this->createWin($user, $game, $date, $guesses);
            $this->service->handleGameWin($user, $result);
        }
        $extraDate = $start->copy()->addDays(365);
        $this->createDailyGame('kcdle', $extraDate);
        $extraResult = $this->createWin($user, 'kcdle', $extraDate, 3);
        $unlocked = $this->service->handleGameWin($user, $extraResult);
        $this->assertUnlocked($unlocked, 'club_legend');
    }
}
