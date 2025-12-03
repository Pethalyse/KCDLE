<?php

namespace App\Services;

use App\Models\DailyGame;
use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class DailyGameSelector
{
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
            'player_id'        => $chosen->id,
            'selected_for_date'=> $date,
        ]);
    }

    /**
     * Retourne les joueurs actifs selon le jeu.
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
     * Récupère les stats (nombre de sélections, dernière date) par player_id pour un jeu donné.
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
     * Tirage aléatoire pondéré.
     *
     * @param Collection $weighted each item: ['player' => Model, 'weight' => float]
     */
    protected function weightedRandom(Collection $weighted)
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

