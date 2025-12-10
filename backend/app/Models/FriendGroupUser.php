<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FriendGroupUser extends Model
{
    protected $fillable = [
        'friend_group_id',
        'user_id',
        'role',
    ];

    public function friendGroup(): BelongsTo
    {
        return $this->belongsTo(FriendGroup::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
