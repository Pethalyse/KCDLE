<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
        'country_default_url'
    ];

    protected static function booted(): void
    {
        static::updating(function (self $player): void {
            if (! $player->isDirty('slug')) {
                return;
            }

            $disk = Storage::disk('public');
            $old = (string) $player->getOriginal('slug');
            $new = (string) $player->getAttribute('slug');

            if ($old === '' || $new === '' || $old === $new) {
                return;
            }

            $exts = ['png', 'webp', 'jpg', 'jpeg'];
            $oldPath = null;
            $extFound = null;

            foreach ($exts as $ext) {
                $p = "players/{$old}.{$ext}";
                if ($disk->exists($p)) {
                    $oldPath = $p;
                    $extFound = $ext;
                    break;
                }
            }

            if (! $oldPath || ! $extFound) {
                return;
            }

            $newPath = "players/{$new}.{$extFound}";

            if ($disk->exists($newPath)) {
                $disk->delete($oldPath);
                return;
            }

            $disk->move($oldPath, $newPath);
        });
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function getCountryDefaultUrlAttribute(): string
    {
        return asset('storage/countries/NN.png');
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
        $slug = (string) $this->getAttribute('slug');
        $exts = ['png', 'webp', 'jpg', 'jpeg'];

        foreach ($exts as $ext) {
            $path = "storage/players/{$slug}.{$ext}";
            if (file_exists(public_path($path))) {
                return asset($path);
            }
        }

        return null;
    }
}
