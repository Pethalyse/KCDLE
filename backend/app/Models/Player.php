<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'display_name',
        'country_code',
        'birthdate',
        'role_id',
    ];

    protected $casts = [
        'birthdate' => 'date',
    ];

    protected $with = [
        'country',
        'role',
    ];

    protected $appends = [
        'image_url',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public static function resolvePlayerModel(string $game, int $playerId): KcdlePlayer|LoldlePlayer|null
    {
        if ($game === 'kcdle') return KcdlePlayer::with(['player'])->find($playerId);
        return LoldlePlayer::with(['player'])->find($playerId);
    }

    public function getImageUrlAttribute(): ?string
    {
        $path = "storage/players/{$this->getAttribute('slug')}.png";
        if (! file_exists(public_path($path))) {
            return null;
        }
        return asset($path);
    }
}
