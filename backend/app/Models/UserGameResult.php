<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGameResult extends Model
{
    protected $fillable = [
        'user_id',
        'daily_game_id',
        'game',
        'guesses_count',
        'won_at',
    ];

    protected $casts = [
        'won_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyGame(): BelongsTo
    {
        return $this->belongsTo(DailyGame::class);
    }

    /**
     * Tout les guesses effectués pour ce daily par ce user.
     */
    public function guesses(): HasMany
    {
        return $this->hasMany(UserGuess::class)
            ->orderBy('guess_order');
    }

    /**
     * Helper pratique pour savoir si cette session est une win.
     */
    public function isWin(): bool
    {
        return $this->getAttribute('won_at') !== null;
    }
}
