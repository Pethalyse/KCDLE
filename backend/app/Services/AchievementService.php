<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\DailyGame;
use App\Models\KcdlePlayer;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserGameResult;
use App\Models\UserGuess;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AchievementService
{
    protected UserGameStatsService $stats;

    /**
     * AchievementService constructor.
     *
     * This service encapsulates the achievement domain logic: it stores the
     * definitions of all achievements, evaluates whether conditions are met
     * when a user wins a game, ensures corresponding rows exist in the database,
     * and unlocks achievements for users.
     *
     * @param UserGameStatsService $stats Service used to compute user statistics
     *                                    required by certain achievement conditions.
     */
    public function __construct(UserGameStatsService $stats)
    {
        $this->stats = $stats;
    }

    /**
     * Evaluate and unlock achievements after a successful game for a user.
     *
     * This method:
     * - loads all achievement definitions,
     * - evaluates the condition of each achievement against the provided user
     *   and game result using isConditionMet(),
     * - ensures a persistent Achievement row exists for each matching key,
     * - checks whether the achievement is already unlocked for the user,
     * - creates a UserAchievement record for each newly unlocked achievement.
     *
     * It returns a collection of Achievement models that have been unlocked
     * during this specific win.
     *
     * @param User           $user   User who has just won a game.
     * @param UserGameResult $result Persisted result of the daily game for this user.
     *
     * @return Collection<int, Achievement> Newly unlocked achievements.
     */
    public function handleGameWin(User $user, UserGameResult $result): Collection
    {
        $unlocked = collect();

        $definitions = $this->definitions();

        $keys = array_keys($definitions);

        foreach ($keys as $key) {
            $definition = $definitions[$key];

            if (! $this->isConditionMet($key, $definition, $user, $result)) {
                continue;
            }

            $achievement = $this->ensureAchievementExists($key, $definition);

            if ($this->isAlreadyUnlocked($user, $achievement)) {
                continue;
            }

            $this->unlockAchievement($user, $achievement);

            $unlocked->push($achievement);
        }

        return $unlocked;
    }

    /**
     * Return the static definitions of all achievements.
     *
     * Each achievement is keyed by a unique string identifier and includes:
     * - 'name'        => string           Display name.
     * - 'description' => string           Human-readable description.
     * - 'game'        => string|null      Game identifier if scoped to a specific game, or null for global.
     *
     * The keys are used internally to:
     * - evaluate eligibility with isConditionMet(),
     * - ensure persistence with ensureAchievementExists(),
     * - map user unlocks in UserAchievement.
     *
     * @return array<string, array{name:string, description:string, game:string|null}>
     */
    protected function definitions(): array
    {
        return [
            'first_win_any' => [
                'name' => 'First blood',
                'description' => 'Remporter votre toute première victoire, tous DLE confondus.',
                'game' => null,
            ],
            'first_win_game_3' => [
                'name' => 'First draft',
                'description' => 'Remporter votre première victoire sur chacun des DLE disponibles.',
                'game' => null,
            ],
            'ten_wins_game' => [
                'name' => 'Habitué du lobby',
                'description' => 'Cumuler 10 victoires sur un DLE.',
                'game' => null,
            ],
            'streak_5_game' => [
                'name' => 'La manita',
                'description' => 'Avoir une streak de 5 sur un DLE.',
                'game' => null,
            ],
            'streak_14_game' => [
                'name' => 'Mur imprenable',
                'description' => 'Avoir une streak de 14 sur un DLE.',
                'game' => null,
            ],
            'perfect_day' => [
                'name' => 'Magicien des ténèbres',
                'description' => 'Trouver le joueur du jour en une seule tentative.',
                'game' => null,
            ],
            'panic_clicker' => [
                'name' => 'Un effort',
                'description' => 'Remporter une partie en 15 tentatives ou plus.',
                'game' => null,
            ],
            'full_roster_tour' => [
                'name' => 'Go next ?',
                'description' => 'Tenter l\'intégralité des joueurs sur le Kcdle avant de trouver enfin le bon.',
                'game' => 'kcdle',
            ],
            'blue_chants' => [
                'name' => 'Chants éternels',
                'description' => 'Cumuler 30 victoires sur un DLE.',
                'game' => null,
            ],
            'graphique_vert' => [
                'name' => 'Graphique tout vert',
                'description' => 'Cumuler 100 victoires tous DLE confondus.',
                'game' => null,
            ],
            'tacticien_galactique' => [
                'name' => 'Galaxies World Champion',
                'description' => 'Cumuler 50 victoires sur un DLE avec une moyenne de guesses inférieure ou égale à 3.',
                'game' => null,
            ],
            'buzzer_beater' => [
                'name' => 'Clutch',
                'description' => 'Trouver le joueur du jour dans la dernière minute avant le reset.',
                'game' => null,
            ],
            'back_to_back_magic' => [
                'name' => 'Back-to-back',
                'description' => 'Enchaîner 2 victoires consécutives en un 1 guess pour un même dle.',
                'game' => null,
            ],
            '5_times_under_2_guesses' => [
                'name' => 'Smurf queue',
                'description' => 'Enchaîner 5 victoires consécutives en 2 tentatives ou moins pour un même dle.',
                'game' => null,
            ],
            'mois_de_folie' => [
                'name' => 'Soir de grande scène',
                'description' => 'Gagner tous les DLE d’un même mois.',
                'game' => null,
            ],
            'pluie_de_perticoins' => [
                'name' => 'Pluie de perticoins',
                'description' => 'Avoir une streak de 10, tous DLE confondus.',
                'game' => null,
            ],
            'club_legend' => [
                'name' => 'Légende du club',
                'description' => 'Cumuler des centaines de victoires, une série monstrueuse et une moyenne de guesses exemplaire.',
                'game' => null,
            ],
        ];
    }

    /**
     * Determine whether a given achievement condition is met for a user and result.
     *
     * The logic inside this method interprets the achievement key and uses
     * data from:
     * - the supplied UserGameResult,
     * - the UserGameStatsService (global stats and per-game stats),
     * - related models such as DailyGame, UserGuess, and KcdlePlayer
     *   when necessary,
     * to decide if the user satisfies the required condition at the moment of
     * this particular win.
     *
     * This method does not persist anything; it only performs the eligibility
     * check for a single achievement definition.
     *
     * @param string         $key        Unique achievement key (e.g. 'first_win_any').
     * @param array{name:string, description:string, game:string|null} $definition
     *                                  Static definition for the achievement.
     * @param User           $user       User to evaluate.
     * @param UserGameResult $result     Game result that triggered the evaluation.
     *
     * @return bool True if the achievement should be unlocked based on this win.
     */
    protected function isConditionMet(string $key, array $definition, User $user, UserGameResult $result): bool
    {
        $userId = $user->getAttribute('id');
        $game = $result->getAttribute('game');

        if ($key === 'first_win_any') {
            return UserGameResult::where('user_id', $userId)
                    ->whereNotNull('won_at')
                    ->count() >= 1;
        }

        if ($key === 'first_win_game_3') {
            return
                UserGameResult::where('user_id', $userId)
                    ->whereNotNull('won_at')
                    ->where('game', "kcdle")
                    ->count() >= 1
                &&
                UserGameResult::where('user_id', $userId)
                    ->whereNotNull('won_at')
                    ->where('game', "lecdle")
                    ->count() >= 1
                &&
                UserGameResult::where('user_id', $userId)
                    ->whereNotNull('won_at')
                    ->where('game', "lfldle")
                    ->count() >= 1;
        }

        if ($key === 'ten_wins_game') {
            return UserGameResult::where('user_id', $userId)
                    ->where('game', $game)
                    ->whereNotNull('won_at')
                    ->count() >= 10;
        }

        if ($key === 'streak_5_game' || $key === 'streak_14_game') {
            $stats = $this->stats->getStatsForUserAndGame($user, $game);
            $streak = (int) ($stats['current_streak'] ?? 0);

            if ($key === 'streak_5_game') {
                return $streak >= 5;
            }

            return $streak >= 14;
        }

        if ($key === 'perfect_day') {
            return (int) $result->getAttribute('guesses_count') === 1;
        }

        if ($key === 'panic_clicker') {
            return (int) $result->getAttribute('guesses_count') >= 15;
        }

        if ($key === 'full_roster_tour') {
            if ($game !== 'kcdle') {
                return false;
            }

            $guesses = UserGuess::where('user_game_result_id', $result->getAttribute('id'))
                ->pluck('player_id');

            if ($guesses->isEmpty()) {
                return false;
            }

            $distinct = $guesses->unique()->count();
            $totalPlayers = KcdlePlayer::all()->count();

            return $distinct >= $totalPlayers;
        }

        if ($key === 'blue_chants') {
            return UserGameResult::where('user_id', $userId)
                    ->where('game', $game)
                    ->whereNotNull('won_at')
                    ->count() >= 30;
        }

        if ($key === 'graphique_vert') {
            return UserGameResult::where('user_id', $userId)
                    ->whereNotNull('won_at')
                    ->count() >= 100;
        }

        if ($key === 'tacticien_galactique') {
            $wins = UserGameResult::where('user_id', $userId)
                ->where('game', $game)
                ->whereNotNull('won_at');

            if ($wins->count() < 50) {
                return false;
            }

            return (float) $wins->avg('guesses_count') <= 3.0;
        }

        if ($key === 'buzzer_beater') {
            $wonAt = $result->getAttribute('won_at');

            $daily = DailyGame::find($result->getAttribute('daily_game_id'));
            if (! $daily) {
                return false;
            }

            $date = $daily->selected_for_date instanceof Carbon
                ? $daily->selected_for_date
                : Carbon::parse($daily->selected_for_date);

            $endOfDay = $date->copy()->endOfDay();

            return $wonAt->diffInSeconds($endOfDay) <= 60;
        }

        if ($key === 'back_to_back_magic') {
            if ((int) $result->getAttribute('guesses_count') !== 1) {
                return false;
            }

            $daily = DailyGame::find($result->getAttribute('daily_game_id'));
            if (! $daily) {
                return false;
            }

            $date = $daily->selected_for_date instanceof Carbon
                ? $daily->selected_for_date
                : Carbon::parse($daily->selected_for_date);

            $yesterday = $date->copy()->subDay()->toDateString();

            $prevDaily = DailyGame::where('game', $game)
                ->whereDate('selected_for_date', $yesterday)
                ->first();

            if (! $prevDaily) {
                return false;
            }

            $prev = UserGameResult::where('user_id', $userId)
                ->where('daily_game_id', $prevDaily->id)
                ->whereNotNull('won_at')
                ->first();

            return $prev && (int) $prev->guesses_count === 1;
        }

        if ($key === '5_times_under_2_guesses') {
            $wins = UserGameResult::where('user_id', $userId)
                ->where('game', $game)
                ->whereNotNull('won_at')
                ->orderBy('id', 'desc')
                ->take(5)
                ->get();

            if ($wins->count() < 5) {
                return false;
            }

            return $wins->every(fn($w) => (int) $w->guesses_count <= 2);
        }

        if ($key === 'mois_de_folie') {
            $daily = DailyGame::find($result->getAttribute('daily_game_id'));
            if (! $daily) {
                return false;
            }

            $date = $daily->selected_for_date instanceof Carbon
                ? $daily->selected_for_date
                : Carbon::parse($daily->selected_for_date);

            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            $days = DailyGame::where('game', $game)
                ->whereBetween('selected_for_date', [$start, $end])
                ->pluck('id');

            if ($days->isEmpty()) {
                return false;
            }

            if($days->count() < $start->diffInDays($end)) {
                return false;
            }

            $wins = UserGameResult::where('user_id', $userId)
                ->whereIn('daily_game_id', $days)
                ->whereNotNull('won_at')
                ->count();

            return $wins === $days->count();
        }

        if ($key === 'pluie_de_perticoins') {
            $daily = DailyGame::find($result->getAttribute('daily_game_id'));
            if (! $daily) {
                return false;
            }

            $date = $daily->selected_for_date instanceof Carbon
                ? $daily->selected_for_date
                : Carbon::parse($daily->selected_for_date);

            $start = $date->copy()->subDays(9)->startOfDay();
            $end = $date->copy()->endOfDay();

            $daysWon = UserGameResult::join('daily_games', 'user_game_results.daily_game_id', '=', 'daily_games.id')
                ->where('user_game_results.user_id', $userId)
                ->whereNotNull('user_game_results.won_at')
                ->whereBetween('daily_games.selected_for_date', [$start, $end])
                ->pluck('daily_games.selected_for_date')
                ->map(fn($d) => Carbon::parse($d)->toDateString())
                ->unique();

            if ($daysWon->count() < 10) {
                return false;
            }

            for ($i = 0; $i < 10; $i++) {
                if (! $daysWon->contains($start->copy()->addDays($i)->toDateString())) {
                    return false;
                }
            }

            return true;
        }

        if ($key === 'club_legend') {
            $totalWins = UserGameResult::where('user_id', $userId)
                ->whereNotNull('won_at')
                ->count();

            if ($totalWins < 365) {
                return false;
            }

            $stats = $this->stats->getStatsForUserAndGame($user, $game);
            $streak = (int) ($stats['current_streak'] ?? 0);

            if ($streak < 30) {
                return false;
            }

            $avg = UserGameResult::where('user_id', $userId)
                ->whereNotNull('won_at')
                ->avg('guesses_count');

            return $avg !== null && (float) $avg <= 3.0;
        }

        return false;
    }

    /**
     * List all achievements with unlock status and global unlock statistics.
     *
     * For each defined achievement, this method ensures there is a corresponding
     * row in the achievements table, then computes:
     * - whether the given user (if any) has unlocked it,
     * - the timestamp when the user unlocked it,
     * - how many users have unlocked it,
     * - the global unlock percentage among all users.
     *
     * The returned collection is a flat list of associative arrays, each with:
     * - 'id'                   => int
     * - 'key'                  => string
     * - 'name'                 => string
     * - 'description'          => string
     * - 'game'                 => string|null
     * - 'unlocked'             => bool
     * - 'unlocked_at'          => string|null ISO 8601 datetime when unlocked, or null.
     * - 'unlocked_percentage'  => float       Percentage of users who unlocked it.
     *
     * If $user is null, the unlock flags are computed as if no user has unlocked anything.
     *
     * @param User|null $user User for whom to compute unlock flags, or null for anonymous.
     *
     * @return Collection<int, array{
     *     id:int,
     *     key:string,
     *     name:string,
     *     description:string,
     *     game:string|null,
     *     unlocked:bool,
     *     unlocked_at:string|null,
     *     unlocked_percentage:float
     * }>
     */
    public function listAllForUser(?User $user): Collection
    {
        $definitions = $this->definitions();
        $keys = array_keys($definitions);

        $achievements = collect();

        foreach ($keys as $key) {
            $definition = $definitions[$key];
            $achievements->push($this->ensureAchievementExists($key, $definition));
        }

        $achievements = $achievements->keyBy('id');

        $unlocked = collect();

        $totalUsers = User::query()->count();
        $unlockedCounts = collect();

        if ($totalUsers > 0 && $achievements->isNotEmpty()) {
            $unlockedCounts = UserAchievement::query()
                ->selectRaw('achievement_id, COUNT(*) as unlocked_count')
                ->whereIn('achievement_id', $achievements->keys()->all())
                ->groupBy('achievement_id')
                ->pluck('unlocked_count', 'achievement_id');
        }

        if ($user) {
            $unlocked = UserAchievement::where('user_id', $user->getAttribute('id'))
                ->whereIn('achievement_id', $achievements->keys()->all())
                ->get()
                ->keyBy('achievement_id');
        }

        return $achievements->map(function (Achievement $achievement) use ($unlocked, $unlockedCounts, $totalUsers) {
            $id = $achievement->getAttribute('id');
            $pivot = $unlocked->get($id);

            $count = (int) ($unlockedCounts[$id] ?? 0);
            $percentage = $totalUsers > 0 ? round($count * 100 / $totalUsers, 2) : 0.0;

            return [
                'id' => $id,
                'key' => $achievement->getAttribute('key'),
                'name' => $achievement->getAttribute('name'),
                'description' => $achievement->getAttribute('description'),
                'game' => $achievement->getAttribute('game'),
                'unlocked' => $pivot !== null,
                'unlocked_at' => $pivot ? $pivot->getAttribute('unlocked_at')?->toIso8601String() : null,
                'unlocked_percentage' => $percentage,
            ];
        })->values();
    }


    /**
     * Ensure that a persistent Achievement row exists for the given key.
     *
     * If an Achievement already exists with the provided key, it is returned
     * as-is. Otherwise, a new row is created using the supplied definition
     * (name, description, game).
     *
     * This method does not perform any user-specific logic; it guarantees
     * a canonical Achievement instance for the given key.
     *
     * @param string $key  Unique achievement key.
     * @param array{name:string, description:string, game:string|null} $definition
     *                     Static definition used to create the record if needed.
     *
     * @return Achievement Existing or newly created achievement model.
     */
    protected function ensureAchievementExists(string $key, array $definition): Achievement
    {
        return Achievement::query()->firstOrCreate(
            ['key' => $key],
            [
                'name' => $definition['name'],
                'description' => $definition['description'],
                'game' => $definition['game'],
            ]
        );
    }

    /**
     * Check whether the given user has already unlocked a specific achievement.
     *
     * This method inspects the UserAchievement pivot table to determine if a
     * row exists for the (user_id, achievement_id) pair.
     *
     * @param User        $user        User to check.
     * @param Achievement $achievement Achievement to check.
     *
     * @return bool True if a corresponding UserAchievement row already exists.
     */
    protected function isAlreadyUnlocked(User $user, Achievement $achievement): bool
    {
        return UserAchievement::query()
            ->where('user_id', $user->getAttribute('id'))
            ->where('achievement_id', $achievement->getAttribute('id'))
            ->exists();
    }

    /**
     * Persist a new unlock of the given achievement for the specified user.
     *
     * This method inserts a new UserAchievement record associating the user
     * and the achievement, and sets the unlock timestamp to the current time.
     * It assumes that isAlreadyUnlocked() has been checked beforehand to avoid
     * duplicates.
     *
     * @param User        $user        User who unlocked the achievement.
     * @param Achievement $achievement Achievement that has been unlocked.
     *
     * @return void
     */
    protected function unlockAchievement(User $user, Achievement $achievement): void
    {
        UserAchievement::query()->create([
            'user_id' => $user->getAttribute('id'),
            'achievement_id' => $achievement->getAttribute('id'),
            'unlocked_at' => now(),
        ]);
    }
}
