<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvpMatchEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'match_id',
        'user_id',
        'type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
    * Get the match that emitted this event.
    *
    * @return BelongsTo<PvpMatch, PvpMatchEvent>
    */
    public function match(): BelongsTo
    {
        return $this->belongsTo(PvpMatch::class, 'match_id');
    }

    /**
    * Get the user that emitted this event when applicable.
    *
    * @return BelongsTo<User, PvpMatchEvent>
    */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
