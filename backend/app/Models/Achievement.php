<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $key
 * @property string $name
 * @property string $description
 * @property string|null $game
 */
class Achievement extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'game',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
