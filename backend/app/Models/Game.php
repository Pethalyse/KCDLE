<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Game extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'icon_slug',
    ];

    protected $appends = [
        'logo_url',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $game): void {
            if (! $game->isDirty('icon_slug')) {
                return;
            }

            $disk = Storage::disk('public');
            $old = (string) $game->getOriginal('icon_slug');
            $new = (string) $game->getAttribute('icon_slug');

            if ($old === '' || $new === '' || $old === $new) {
                return;
            }

            $exts = ['png', 'webp', 'jpg', 'jpeg'];
            $oldPath = null;
            $extFound = null;

            foreach ($exts as $ext) {
                $p = "games/{$old}.{$ext}";
                if ($disk->exists($p)) {
                    $oldPath = $p;
                    $extFound = $ext;
                    break;
                }
            }

            if (! $oldPath || ! $extFound) {
                return;
            }

            $newPath = "games/{$new}.{$extFound}";

            if ($disk->exists($newPath)) {
                $disk->delete($oldPath);
                return;
            }

            $disk->move($oldPath, $newPath);
        });
    }

    public function getLogoUrlAttribute(): ?string
    {
        $slug = (string) $this->getAttribute('icon_slug');
        $exts = ['png', 'webp', 'jpg', 'jpeg'];

        foreach ($exts as $ext) {
            $path = "storage/games/{$slug}.{$ext}";
            if (file_exists(public_path($path))) {
                return asset($path);
            }
        }

        return null;
    }
}
