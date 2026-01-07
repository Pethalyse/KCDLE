<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class FriendGroup extends Model
{
    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'join_code',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friend_group_users')
            ->withPivot('role')
            ->withTimestamps();
    }
}
