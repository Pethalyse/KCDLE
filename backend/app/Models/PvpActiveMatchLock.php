<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PvpActiveMatchLock extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'match_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Get the user owning this active match lock.
     *
     * @return BelongsTo<User, PvpActiveMatchLock>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the match referenced by this active match lock.
     *
     * @return BelongsTo<PvpMatch, PvpActiveMatchLock>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(PvpMatch::class, 'match_id');
    }
}
