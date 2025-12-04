<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function getLogoUrlAttribute(): ?string
    {
        $path = "storage/teams/{$this->getAttribute('slug')}.png";
        if (! file_exists(public_path($path))) {
            return null;
        }
        return asset($path);
    }

}
