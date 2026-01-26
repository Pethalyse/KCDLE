<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Application user model.
 *
 * This model also stores lightweight profile customization attributes used by the frontend,
 * such as an avatar stored on the public disk and a profile-picture frame color.
 */
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'discord_id',
        'password',
        'avatar_path',
        'avatar_frame_color',
    ];

    /**
     * Attributes appended to the model's array / JSON form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Resolve the public URL for the user's avatar.
     *
     * If the user has not uploaded a custom avatar yet, the default avatar is returned.
     *
     * @return string
     */
    public function getAvatarUrlAttribute(): string
    {
        $path = (string) ($this->getAttribute('avatar_path') ?: 'users/defaut.png');

        return asset('storage/' . ltrim($path, '/'));
    }

    /**
     * Resolve the avatar frame color.
     *
     * @param string|null $value Stored database value.
     *
     * @return string
     */
    public function getAvatarFrameColorAttribute(?string $value): string
    {
        $v = $value ?? '';

        return $v !== '' ? $v : '#3B82F6';
    }

    /**
     * Send the email verification notification.
     *
     * This method overrides Laravel's default notification so the email content
     * and call-to-action are tailored to the application's branding and flow.
     *
     * @return void
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    /**
     * Send the password reset notification.
     *
     * This overrides Laravel's default notification to ensure the reset link
     * points to the SPA frontend instead of a Blade route.
     *
     * @param string $token Password reset token.
     *
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return (bool) $this->is_admin;
        }

        return false;
    }

    public function friendGroups(): BelongsToMany
    {
        return $this->belongsToMany(FriendGroup::class, 'friend_group_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function achievements(): BelongsToMany
    {
        return $this->belongsToMany(Achievement::class, 'user_achievements')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
