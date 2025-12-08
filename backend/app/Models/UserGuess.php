<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGuess extends Model
{
    protected $fillable = [
        'user_game_result_id',
        'guess_order',
        'player_id',
    ];

    public function result(): BelongsTo
    {
        return $this->belongsTo(UserGameResult::class, 'user_game_result_id');
    }
}
