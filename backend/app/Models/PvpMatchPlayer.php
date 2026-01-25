<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PvP match participant record.
 *
 * Stores the seat, points and activity timestamps for a user in a given match.
 * Activity timestamps are used for AFK detection (presence via heartbeat and idle via actions).
 *
 * @property int $id
 * @property int $match_id
 * @property int $user_id
 * @property int $seat
 * @property int $points
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $last_action_at
 *
 * @property-read PvpMatch $match
 * @property-read User $user
 */
class PvpMatchPlayer extends Model
{
    protected $fillable = [
        'match_id',
        'user_id',
        'seat',
        'points',
        'last_seen_at',
        'last_action_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_action_at' => 'datetime',
    ];

    /**
     * Get the match this player belongs to.
     *
     * @return BelongsTo<PvpMatch, PvpMatchPlayer>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(PvpMatch::class, 'match_id');
    }

    /**
     * Get the user profile for this match player.
     *
     * @return BelongsTo<User, PvpMatchPlayer>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
