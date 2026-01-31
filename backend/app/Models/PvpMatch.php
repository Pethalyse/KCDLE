<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * PvP match record.
 *
 * A match is composed of:
 * - a game identifier,
 * - a best-of format and current round index,
 * - a rounds configuration array (JSON),
 * - a mutable state blob used by round handlers (JSON),
 * - timestamps for match start and finish.
 *
 * Matches can originate from the public queue or from a private lobby.
 * When the match is created from a lobby, the lobby row will reference it.
 *
 * @property int $id
 * @property string $game
 * @property string $status
 * @property int $best_of
 * @property int $current_round
 * @property array $rounds
 * @property array|null $state
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Collection<int, PvpMatchPlayer> $players
 * @property-read Collection<int, PvpMatchEvent> $events
 * @property-read PvpLobby|null $lobby
 */
class PvpMatch extends Model
{
    protected $fillable = [
        'game',
        'status',
        'best_of',
        'current_round',
        'rounds',
        'state',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'rounds' => 'array',
        'state' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Get all players participating in this match.
     *
     * @return HasMany<PvpMatchPlayer, PvpMatch>
     */
    public function players(): HasMany
    {
        return $this->hasMany(PvpMatchPlayer::class, 'match_id');
    }

    /**
     * Get all events emitted for this match.
     *
     * @return HasMany<PvpMatchEvent, PvpMatch>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PvpMatchEvent::class, 'match_id');
    }

    /**
     * Get the lobby that created this match when applicable.
     *
     * When a match is started from a lobby, the lobby row stores the match_id.
     * Queue matches have no related lobby.
     *
     * @return HasOne<PvpLobby>
     */
    public function lobby(): HasOne
    {
        return $this->hasOne(PvpLobby::class, 'match_id');
    }
}
