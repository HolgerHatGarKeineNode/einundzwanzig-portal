<?php

namespace App\Models;

use App\Models\Concerns\HasOsmReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;
    use HasOsmReference;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'language_codes' => 'array',
        'osm_id' => 'integer',
        'osm_lat' => 'float',
        'osm_lon' => 'float',
    ];

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * Verwaltungsebene 1 (ISO 3166-2) — nur fuer freigeschaltete Laender gepflegt.
     */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }
}
