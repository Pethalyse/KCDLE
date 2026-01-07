<?php

namespace App\Services\Dle;

use App\Models\KcdlePlayer;
use App\Models\LoldlePlayer;
use Illuminate\Database\Eloquent\Collection;

/**
 * Provides access to game players across supported DLE games.
 *
 * This service centralizes player listing rules (league filters, active flag),
 * and offers helpers useful for PvP such as selecting a random secret player id.
 */
class GamePlayerService
{
    /**
     * List players for a given game with optional active-only filter.
     *
     * @param string $game       Game identifier ('kcdle', 'lfldle', 'lecdle').
     * @param bool   $onlyActive Whether to return only players marked as active.
     *
     * @return Collection<int, KcdlePlayer|LoldlePlayer>
     */
    public function listPlayers(string $game, bool $onlyActive = true): Collection
    {
        return match ($game) {
            'kcdle' => $this->getKcdlePlayers($onlyActive),
            'lfldle' => $this->getLflPlayers($onlyActive),
            'lecdle' => $this->getLecPlayers($onlyActive),
            default => collect(),
        };
    }

    /**
     * Pick a random wrapper player id for a given game.
     *
     * This is used by PvP Classic rounds to choose a shared secret for both players.
     *
     * @param string $game       Game identifier.
     * @param bool   $onlyActive Whether to pick among active players only.
     *
     * @return int Random wrapper id.
     */
    public function randomPlayerId(string $game, bool $onlyActive = true): int
    {
        return match ($game) {
            'kcdle' => (int) KcdlePlayer::query()
                ->when($onlyActive, fn ($q) => $q->where('active', true))
                ->inRandomOrder()
                ->value('id'),
            'lfldle' => (int) LoldlePlayer::query()
                ->whereHas('league', fn ($q) => $q->where('code', 'LFL'))
                ->when($onlyActive, fn ($q) => $q->where('active', true))
                ->inRandomOrder()
                ->value('id'),
            'lecdle' => (int) LoldlePlayer::query()
                ->whereHas('league', fn ($q) => $q->where('code', 'LEC'))
                ->when($onlyActive, fn ($q) => $q->where('active', true))
                ->inRandomOrder()
                ->value('id'),
            default => 0,
        };
    }

    /**
     * Retrieve KCDLE players with an optional active-only filter.
     *
     * @param bool $onlyActive Whether to return only active players.
     *
     * @return Collection<int, KcdlePlayer>
     */
    public function getKcdlePlayers(bool $onlyActive): Collection
    {
        return KcdlePlayer::query()
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('id')
            ->get();
    }

    /**
     * Retrieve LOL DLE players for LFL with an optional active-only filter.
     *
     * @param bool $onlyActive Whether to return only active players.
     *
     * @return Collection<int, LoldlePlayer>
     */
    public function getLflPlayers(bool $onlyActive): Collection
    {
        return LoldlePlayer::query()
            ->whereHas('league', fn ($q) => $q->where('code', 'LFL'))
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('id')
            ->get();
    }

    /**
     * Retrieve LOL DLE players for LEC with an optional active-only filter.
     *
     * @param bool $onlyActive Whether to return only active players.
     *
     * @return Collection<int, LoldlePlayer>
     */
    public function getLecPlayers(bool $onlyActive): Collection
    {
        return LoldlePlayer::query()
            ->whereHas('league', fn ($q) => $q->where('code', 'LEC'))
            ->when($onlyActive, fn ($q) => $q->where('active', true))
            ->orderBy('id')
            ->get();
    }
}
