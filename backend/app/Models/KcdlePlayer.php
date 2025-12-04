<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KcdlePlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'game_id',
        'current_team_id',
        'previous_team_before_kc_id',
        'first_official_year',
        'trophies_count',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $with = [
        'player',
        'game',
        'currentTeam',
        'previousTeam',
    ];

    protected $appends = [
        'team_default_url'
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function previousTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'previous_team_before_kc_id');
    }

    public function getTeamDefaultUrlAttribute(): string
    {
        return asset('storage/teams/none.png');
    }

    public function incrementTrophiesCount(): void {
        $this->increment('trophies_count');
    }

    public function isUsedInFutureDailyGames(): bool
    {
        return DailyGame::query()
            ->where('game', 'kcdle')
            ->where('player_id', $this->getAttribute("id"))
            ->whereDate('selected_for_date', '>=', today())
            ->exists();
    }

    public function cannotDeactivate(): bool
    {
        return $this->isUsedInFutureDailyGames();
    }
}
