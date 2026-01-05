<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    * @return HasMany<PvpMatchPlayer>
    */
    public function players(): HasMany
    {
        return $this->hasMany(PvpMatchPlayer::class, 'match_id');
    }

    /**
    * Get all events emitted for this match.
    *
    * @return HasMany<PvpMatchEvent>
    */
    public function events(): HasMany
    {
        return $this->hasMany(PvpMatchEvent::class, 'match_id');
    }
}
