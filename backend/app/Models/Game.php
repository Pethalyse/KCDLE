<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function getLogoUrlAttribute(): ?string
    {
        $path = "storage/games/{$this->getAttribute('icon_slug')}.png";
        if (! file_exists(public_path($path))) {
            return null;
        }
        return asset($path);
    }
}
