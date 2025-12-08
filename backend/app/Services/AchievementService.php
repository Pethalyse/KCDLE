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
     * @param UserGameStatsService $stats
     */
    public function __construct(UserGameStatsService $stats)
    {
        $this->stats = $stats;
    }

    /**
     * Handle achievements for a user when a game is won.
     *
     * @param User $user
     * @param UserGameResult $result
     * @return Collection<int, Achievement>
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
     * Get achievement definitions.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function definitions(): array
    {
        return [
            'first_win_any' => [
                'name' => 'First blood',
                'description' => 'Remporter votre toute première victoire, tous DLE confondus.',
                'game' => null,
            ],
            'first_win_game' => [
                'name' => 'First draft',
                'description' => 'Remporter votre première victoire sur ce DLE.',
                'game' => null,
            ],
            'ten_wins_game' => [
                'name' => 'Habitué du lobby',
                'description' => 'Cumuler 10 victoires sur ce DLE.',
                'game' => null,
            ],
            'streak_5_game' => [
                'name' => 'La manita',
                'description' => 'Gagner 5 jours d’affilée sur ce DLE.',
                'game' => null,
            ],
            'streak_7_game' => [
                'name' => 'Mur imprenable',
                'description' => 'Gagner 7 jours d’affilée sur ce DLE.',
                'game' => null,
            ],
            'perfect_day' => [
                'name' => 'Magicien des ténèbres',
                'description' => 'Trouver le joueur du jour en une seule tentative.',
                'game' => null,
            ],
            'panic_clicker' => [
                'name' => 'Un effort',
                'description' => 'Remporter une partie en quinze tentatives ou plus.',
                'game' => null,
            ],
            'full_roster_tour' => [
                'name' => 'Go next ?',
                'description' => 'Tenter l\'intégralité des joueurs sur le Kcdle avant de trouver enfin le bon.',
                'game' => 'kcdle',
            ],
            'blue_chants' => [
                'name' => 'Chants éternels',
                'description' => 'Cumuler 30 victoires sur ce DLE.',
                'game' => null,
            ],
            'graphique_vert' => [
                'name' => 'Graphique tout vert',
                'description' => 'Cumuler 100 victoires tous DLE confondus.',
                'game' => null,
            ],
            'tacticien_galactique' => [
                'name' => 'Galaxies World Champion',
                'description' => 'Cumuler 50 victoires sur ce DLE avec une moyenne de guesses inférieure ou égale à 3.',
                'game' => null,
            ],
            'buzzer_beater' => [
                'name' => 'Clutch',
                'description' => 'Trouver le joueur du jour dans la dernière minute avant le reset.',
                'game' => null,
            ],
            'back_to_back_magic' => [
                'name' => 'Back-to-back',
                'description' => 'Enchaîner deux victoires consécutives en un seul guess.',
                'game' => null,
            ],
            '5_times_under_3_guesses' => [
                'name' => 'Smurf queue',
                'description' => 'Enchaîner cinq victoires consécutives en trois tentatives ou moins.',
                'game' => null,
            ],
            'mois_de_folie' => [
                'name' => 'Soir de grande scène',
                'description' => 'Gagner tous les DLE d’un même mois sur ce mode.',
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

    protected function isConditionMet(string $key, array $definition, User $user, UserGameResult $result): bool
    {
        $userId = $user->getAttribute('id');
        $game = $result->getAttribute('game');

        if ($key === 'first_win_any') {
            return UserGameResult::where('user_id', $userId)
                    ->whereNotNull('won_at')
                    ->count() === 1;
        }

        if ($key === 'first_win_game') {
            return UserGameResult::where('user_id', $userId)
                    ->where('game', $game)
                    ->whereNotNull('won_at')
                    ->count() === 1;
        }

        if ($key === 'ten_wins_game') {
            return UserGameResult::where('user_id', $userId)
                    ->where('game', $game)
                    ->whereNotNull('won_at')
                    ->count() >= 10;
        }

        if ($key === 'streak_5_game' || $key === 'streak_7_game') {
            $stats = $this->stats->getStatsForUserAndGame($user, $game);
            $streak = (int) ($stats['current_streak'] ?? 0);

            if ($key === 'streak_5_game') {
                return $streak >= 5;
            }

            return $streak >= 7;
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

            return $endOfDay->diffInSeconds($wonAt, false) <= 60;
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

        if ($key === '5_times_under_3_guesses') {
            $wins = UserGameResult::where('user_id', $userId)
                ->where('game', $game)
                ->whereNotNull('won_at')
                ->orderBy('id', 'desc')
                ->take(5)
                ->get();

            if ($wins->count() < 5) {
                return false;
            }

            return $wins->every(fn($w) => (int) $w->guesses_count <= 3);
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
     * List all achievements with user unlock status.
     *
     * @param User|null $user
     * @return Collection<int, array<string, mixed>>
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

        if ($user) {
            $unlocked = UserAchievement::where('user_id', $user->getAttribute('id'))
                ->whereIn('achievement_id', $achievements->keys()->all())
                ->get()
                ->keyBy('achievement_id');
        }

        return $achievements->map(function (Achievement $achievement) use ($unlocked) {
            $pivot = $unlocked->get($achievement->getAttribute('id'));

            return [
                'id' => $achievement->getAttribute('id'),
                'key' => $achievement->getAttribute('key'),
                'name' => $achievement->getAttribute('name'),
                'description' => $achievement->getAttribute('description'),
                'game' => $achievement->getAttribute('game'),
                'unlocked' => $pivot !== null,
                'unlocked_at' => $pivot ? $pivot->getAttribute('unlocked_at')?->toIso8601String() : null,
            ];
        })->values();
    }

    /**
     * Ensure an achievement row exists in database for a given key.
     *
     * @param string $key
     * @param array<string, mixed> $definition
     * @return Achievement
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
     * Check if an achievement is already unlocked for the user.
     *
     * @param User $user
     * @param Achievement $achievement
     * @return bool
     */
    protected function isAlreadyUnlocked(User $user, Achievement $achievement): bool
    {
        return UserAchievement::query()
            ->where('user_id', $user->getAttribute('id'))
            ->where('achievement_id', $achievement->getAttribute('id'))
            ->exists();
    }

    /**
     * Unlock an achievement for the user.
     *
     * @param User $user
     * @param Achievement $achievement
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
