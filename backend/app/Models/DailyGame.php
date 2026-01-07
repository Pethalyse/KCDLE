<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class DailyGame extends Model
{
    protected $fillable = [
        'game',
        'player_id',
        'selected_for_date',
        'solvers_count',
        'total_guesses',
    ];

    protected $casts = [
        'selected_for_date' => 'date',
    ];

    protected $appends = [
        'average_guesses',
        'game_label',
        'player_display_name',
        'player_model'
    ];

    public function getAverageGuessesAttribute(): ?float
    {
        if ($this->getAttribute('solvers_count') === 0) {
            return null;
        }

        return $this->getAttribute('total_guesses') / $this->getAttribute('solvers_count');
    }

    public function getGameLabelAttribute(): string
    {
        return match ($this->getAttribute('game')) {
            'kcdle'  => 'KCDLE',
            'lfldle' => 'LFLDLE',
            'lecdle' => 'LECDLE',
            default  => strtoupper($this->getAttribute('game')),
        };
    }

    public function getPlayerDisplayNameAttribute(): ?string
    {
        return match ($this->getAttribute('game')) {
            'kcdle'  => KcdlePlayer::with('player')->find($this->getAttribute("player_id"))?->player?->display_name,
            'lfldle',
            'lecdle' => LoldlePlayer::with('player')->find($this->getAttribute("player_id"))?->player?->display_name,
            default  => null,
        };
    }

    public function getPlayerModelAttribute(): ?Model
    {
        if ($this->getAttribute('game') === 'kcdle') {
            return KcdlePlayer::with(['player'])->find($this->getAttribute("player_id"));
        }

        if (in_array($this->getAttribute('game'), ['lfldle', 'lecdle'], true)) {
            return LoldlePlayer::with(['player'])->find($this->getAttribute("player_id"));
        }

        return null;
    }

    protected static function booted(): void
    {
        static::deleting(function (DailyGame $daily) {
            if (! $daily->getAttribute('selected_for_date')->isFuture()) {
                throw new RuntimeException('Cannot delete past or current daily games.');
            }
        });
    }
}
