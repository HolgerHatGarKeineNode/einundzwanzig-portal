<?php

namespace App\Models;

use Akuechler\Geoly;
use App\Models\Concerns\SetsCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cookie;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class City extends Model
{
    use Geoly;
    use HasFactory;
    use HasSlug;
    use SetsCreatedBy;

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
        'country_id' => 'integer',
        'osm_relation' => 'json',
        'simplified_geojson' => 'json',
    ];

    /**
     * Findet eine Stadt anhand ihres Namens; Städtenamen sind global eindeutig.
     */
    public static function findByName(string $name): ?self
    {
        return static::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
    }

    /**
     * Legt die Stadt an oder liefert die bereits vorhandene gleichnamige Stadt zurueck.
     *
     * Staedte sind Stammdaten mit einem globalen Unique-Constraint auf dem Namen:
     * ein zweiter Anlageversuch ist kein Fehler, sondern liefert den Bestand.
     * `wasRecentlyCreated` unterscheidet Neuanlage von Treffer.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createOrFindByName(array $attributes): self
    {
        if ($existing = static::findByName((string) $attributes['name'])) {
            return $existing;
        }

        try {
            return static::create($attributes);
        } catch (UniqueConstraintViolationException $exception) {
            return static::findByName((string) $attributes['name']) ?? throw $exception;
        }
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(['country.code', 'name'])
            ->saveSlugsTo('slug')
            ->usingLanguage(Cookie::get('lang', config('app.locale')));
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function courseEvents(): HasMany
    {
        return $this->hasMany(CourseEvent::class);
    }

    public function bitcoinEvents(): HasMany
    {
        return $this->hasMany(BitcoinEvent::class);
    }

    public function meetups()
    {
        return $this->hasMany(Meetup::class);
    }
}
