<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Verwaltungsebene 1 eines Landes (ISO 3166-2) — US-Bundesstaat, Bundesland, Provinz.
 */
class Region extends Model
{
    use HasFactory;

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
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    /**
     * Loest ein URL-Segment wie "in" gegen einen Laendercode wie "us" auf.
     *
     * Bewusst `null` statt einer Exception: der Aufrufer entscheidet, ob daraus ein 404
     * wird — die Routen tun das, damit ein unbekannter Code nicht als stiller Leerfilter
     * durchrutscht.
     */
    public static function findByCountryCodeAndCode(string $countryCode, string $code): ?self
    {
        /*
         * Case-insensitiv: `countries.code` ist je nach Datenbestand "us" oder "US", und
         * SQLite vergleicht anders als MySQL exakt.
         */
        return static::query()
            ->whereHas('country', fn ($query) => $query->whereRaw('LOWER(code) = ?', [mb_strtolower($countryCode)]))
            ->where('code', mb_strtolower($code))
            ->first();
    }

    /**
     * Liest das optionale `region`-Segment der aktuellen Route.
     *
     * Ohne Segment `null` — die Seite verhaelt sich dann exakt wie vor der Einfuehrung
     * von Regionen. Mit einem Segment, das dem Land nicht bekannt ist, ein 404: ein
     * unbekannter Code darf nicht als stiller Leerfilter durchrutschen, sonst sieht eine
     * vertippte URL aus wie "hier gibt es keine Meetups".
     */
    public static function fromRouteOrFail(string $countryCode): ?self
    {
        $code = request()->route('region');

        if (! is_string($code) || $code === '') {
            return null;
        }

        return static::findByCountryCodeAndCode($countryCode, $code)
            ?? abort(404);
    }

    /**
     * Der volle ISO-3166-2-Code, z. B. "US-IN".
     */
    public function isoCode(): string
    {
        return mb_strtoupper($this->country->code.'-'.$this->code);
    }
}
