<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PendingGuess extends Model
{
    protected $fillable = [
        'anon_key',
        'daily_game_id',
        'game',
        'player_id',
        'guess_order',
        'correct',
    ];

    public function dailyGame(): BelongsTo
    {
        return $this->belongsTo(DailyGame::class);
    }
}
