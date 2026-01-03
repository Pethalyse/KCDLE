<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
