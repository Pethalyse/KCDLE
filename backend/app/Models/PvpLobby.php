<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $host_user_id
 * @property int|null $guest_user_id
 * @property string $game
 * @property int $best_of
 * @property string $status
 * @property string $code
 * @property int|null $match_id
 * @property Carbon|null $started_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PvpLobby extends Model
{
    protected $table = 'pvp_lobbies';

    protected $fillable = [
        'host_user_id',
        'guest_user_id',
        'game',
        'best_of',
        'status',
        'code',
        'match_id',
        'started_at',
        'closed_at',
    ];

    protected $casts = [
        'best_of' => 'int',
        'host_user_id' => 'int',
        'guest_user_id' => 'int',
        'match_id' => 'int',
        'started_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, PvpLobby>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    /**
     * @return BelongsTo<User, PvpLobby>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_user_id');
    }

    /**
     * @return BelongsTo<PvpMatch, PvpLobby>
     */
    public function match(): BelongsTo
    {
        return $this->belongsTo(PvpMatch::class, 'match_id');
    }
}
