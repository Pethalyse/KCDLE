<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoldlePlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'league_id',
        'team_id',
        'lol_role',
        'active',
    ];

    protected $with = [
        'player',
        'team',
        'league',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $appends = [
        'lol_role_url'
    ];

    public function league() : BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isUsedInFutureDailyGames(): bool
    {
        return DailyGame::query()
            ->whereIn('game', ['lfldle', 'lecdle'])
            ->where('player_id', $this->getAttribute("id"))
            ->whereDate('selected_for_date', '>=', today())
            ->exists();
    }

    public function getLolRoleUrlAttribute(): ?string
    {
        $path = "storage/roles/{$this->getAttribute('lol_role')}.png";
        if (! file_exists(public_path($path))) {
            return null;
        }
        return asset($path);
    }

    public function cannotDeactivate(): bool
    {
        return $this->isUsedInFutureDailyGames();
    }
}
