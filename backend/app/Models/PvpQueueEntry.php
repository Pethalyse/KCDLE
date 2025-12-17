<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvpQueueEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'game',
        'best_of',
        'created_at',
    ];

    protected $casts = [
        'best_of' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the user who is queued for PvP matchmaking.
     *
     * @return BelongsTo<User, PvpQueueEntry>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
