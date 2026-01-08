<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'display_name',
        'short_name',
        'country_code',
        'is_karmine_corp',
    ];

    protected $casts = [
        'is_karmine_corp' => 'boolean',
    ];

    protected $with = [
        'country',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $team): void {
            if (! $team->isDirty('slug')) {
                return;
            }

            $disk = Storage::disk('public');
            $old = (string) $team->getOriginal('slug');
            $new = (string) $team->getAttribute('slug');

            if ($old === '' || $new === '' || $old === $new) {
                return;
            }

            $exts = ['png', 'webp', 'jpg', 'jpeg'];
            $oldPath = null;
            $extFound = null;

            foreach ($exts as $ext) {
                $p = "teams/{$old}.{$ext}";
                if ($disk->exists($p)) {
                    $oldPath = $p;
                    $extFound = $ext;
                    break;
                }
            }

            if (! $oldPath || ! $extFound) {
                return;
            }

            $newPath = "teams/{$new}.{$extFound}";

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

    public function getLogoUrlAttribute(): ?string
    {
        $slug = (string) $this->getAttribute('slug');
        $exts = ['png', 'webp', 'jpg', 'jpeg'];

        foreach ($exts as $ext) {
            $path = "storage/teams/{$slug}.{$ext}";
            if (file_exists(public_path($path))) {
                return asset($path);
            }
        }

        return null;
    }
}
