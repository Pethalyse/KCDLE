<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $lobby_id
 * @property int|null $user_id
 * @property string $type
 * @property array|null $payload
 * @property \Illuminate\Support\Carbon|null $created_at
 */
class PvpLobbyEvent extends Model
{
    public $timestamps = false;

    protected $table = 'pvp_lobby_events';

    protected $fillable = [
        'lobby_id',
        'user_id',
        'type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'lobby_id' => 'int',
        'user_id' => 'int',
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<PvpLobby, PvpLobbyEvent>
     */
    public function lobby(): BelongsTo
    {
        return $this->belongsTo(PvpLobby::class, 'lobby_id');
    }

    /**
     * @return BelongsTo<User, PvpLobbyEvent>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
