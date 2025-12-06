<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    use HasFactory;

    protected $table = 'countries';
    protected $primaryKey = 'code';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = true;

    protected $fillable = [
        'code',
        'name',
    ];

    protected $appends = [
        'flag_url',
    ];

    public function getFlagUrlAttribute(): ?string
    {
        $path = "storage/countries/{$this->getAttribute('code')}.png";
        if (! file_exists(public_path($path))) {
            return null;
        }
        return asset($path);
    }
}
