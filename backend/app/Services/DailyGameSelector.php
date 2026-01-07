<?php

namespace App\Services;

use App\Models\DailyGame;
use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use App\Models\Player;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

class DailyGameSelector
{
    /**
     * Select (or create) the DailyGame entry for a given game and date.
     *
     * If a DailyGame already exists for the specified game and date, it is
     * returned directly. Otherwise, this method:
     * - fetches all eligible active players for the game using getEligiblePlayers(),
     * - fetches selection statistics for the game using getStatsForGame(),
     * - computes a weight for each player based on:
     *   - how many times they have already been selected,
     *   - how long ago they were last selected,
     * - performs a weighted random draw among players with positive weight
     *   (or uniform random among all players if all weights are zero),
     * - creates and returns a new DailyGame row for the chosen player and date.
     *
     * If no eligible players exist, a RuntimeException is thrown.
     *
     * @param string       $game Game identifier (e.g. 'kcdle', 'lfldle', 'lecdle').
     * @param Carbon $date Date for which the daily game is being selected.
     *
     * @return DailyGame Existing or newly created daily game for the date.
     *
     * @throws RuntimeException If no eligible player can be selected.
     */
    public function selectForGame(string $game, Carbon $date): DailyGame
    {
        $existing = DailyGame::where('game', $game)
            ->whereDate('selected_for_date', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        $players = $this->getEligiblePlayers($game);

        if ($players->isEmpty()) {
            throw new RuntimeException("Aucun joueur éligible pour le jeu $game");
        }

        $stats = $this->getStatsForGame($game);

        $weighted = $players->map(function ($player) use ($stats, $date) {
            $playerId = $player->id;

            $playerStats = $stats[$playerId] ?? [
                'times_selected' => 0,
                'last_selected_at' => null,
            ];

            $times = $playerStats['times_selected'];
            $last = $playerStats['last_selected_at'];

            $daysSinceLast = $last
                ? max(1, $last->diffInDays($date))
                : 30;

            $freqFactor = 1 / (1 + $times);
            $recencyFactor = min(1, $daysSinceLast / 30);

            $weight = $freqFactor * $recencyFactor;

            return [
                'player' => $player,
                'weight' => $weight,
            ];
        })->filter(fn ($row) => $row['weight'] > 0);

        if ($weighted->isEmpty()) {
            $chosen = $players->random();
        } else {
            $chosen = $this->weightedRandom($weighted);
        }

        return DailyGame::create([
            'game'             => $game,
            'player_id'        => $chosen->getAttribute("id"),
            'selected_for_date'=> $date,
        ]);
    }

    /**
     * Retrieve all active players eligible for selection for the given game.
     *
     * Depending on the $game identifier, this method queries:
     * - 'kcdle'  => KcdlePlayer::query()->where('active', true)
     * - 'lfldle' => LoldlePlayer::query()->where('active', true)->whereHas('league', code = 'LFL')
     * - 'lecdle' => LoldlePlayer::query()->where('active', true)->whereHas('league', code = 'LEC')
     *
     * For any unknown game value, an empty collection is returned.
     *
     * @param string $game Game identifier.
     *
     * @return Collection<int, Model> Collection of active player models.
     */
    protected function getEligiblePlayers(string $game): Collection
    {
        return match ($game) {
            'kcdle' => KcdlePlayer::query()
                ->where('active', true)
                ->get(),

            'lfldle' => LoldlePlayer::query()
                ->where('active', true)
                ->whereHas('league', fn ($q) => $q->where('code', 'LFL'))
                ->get(),

            'lecdle' => LoldlePlayer::query()
                ->where('active', true)
                ->whereHas('league', fn ($q) => $q->where('code', 'LEC'))
                ->get(),

            default => collect(),
        };
    }

    /**
     * Retrieve historical selection statistics per player for a given game.
     *
     * This method aggregates the daily_games table for the specified game and
     * returns an associative array keyed by player_id. For each player, it
     * provides:
     * - 'times_selected'   => int                Number of times selected as daily.
     * - 'last_selected_at' => Carbon|null Last selection date, or null if never selected.
     *
     * These statistics are used by selectForGame() to compute the selection
     * weight of each player.
     *
     * @param string $game Game identifier.
     *
     * @return array<int, array{times_selected:int, last_selected_at:Carbon|null}>
     */
    protected function getStatsForGame(string $game): array
    {
        return DailyGame::query()
            ->where('game', $game)
            ->selectRaw('player_id, COUNT(*) as times_selected, MAX(selected_for_date) as last_selected_at')
            ->groupBy('player_id')
            ->get()
            ->mapWithKeys(function ($row) {
                return [
                    $row->player_id => [
                        'times_selected'  => (int) $row->times_selected,
                        'last_selected_at'=> $row->last_selected_at ? Carbon::parse($row->last_selected_at) : null,
                    ],
                ];
            })
            ->toArray();
    }

    /**
     * Perform a weighted random draw from a collection of items.
     *
     * Each item in the collection must be an associative array of the form:
     * - 'player' => mixed   The value to return if this item is selected.
     * - 'weight' => float   Non-negative weight for the item.
     *
     * The method samples a random value in [0, sum(weights)] and walks through
     * the items, accumulating weights until the threshold is reached. If, for
     * any reason, the loop does not return a player, the first item is returned
     * as a fallback.
     *
     * @param Collection<int, array{player:mixed, weight:float}> $weighted
     *
     * @return KcdlePlayer|LoldlePlayer The 'player' value of the selected entry.
     */
    protected function weightedRandom(Collection $weighted): KcdlePlayer|LoldlePlayer
    {
        $total = $weighted->sum('weight');
        $rand = mt_rand() / mt_getrandmax() * $total;

        $acc = 0;
        foreach ($weighted as $row) {
            $acc += $row['weight'];
            if ($rand <= $acc) {
                return $row['player'];
            }
        }

        return $weighted->first()['player'];
    }
}

