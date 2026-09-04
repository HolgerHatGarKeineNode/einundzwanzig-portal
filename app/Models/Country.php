<?php

namespace App\Models;

use App\Console\Commands\ImportRegions;
use App\Models\Concerns\HasOsmReference;
use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Vergleicht `countries.code` gegen ein URL-Segment — case-insensitiv.
     *
     * `countries.code` ist gross oder klein geschrieben, je nach Bestand: lokal steht
     * "DE" neben "us", und der Regions-Import hat denselben Unterschied schon in der
     * Produktion angetroffen ({@see ImportRegions}). Die URLs des
     * Portals sind dagegen immer klein. Ein `where('code', 'de')` haengt damit an der
     * Kollation: MySQL vergleicht mit utf8mb4_*_ci zufaellig richtig, SQLite exakt — die
     * Abfrage ist eine Wette auf den Bestand, keine Aussage ueber ihn. Genau daran
     * zeigten die landesbezogenen Badges der Sidebar durchgaengig 0 (Issue #58).
     *
     * Dieselbe Form benutzt {@see Region::findByCountryCodeAndCode()} seit dem
     * Regions-Import; sie steht hier, damit nicht jede Aufrufstelle sie neu formuliert.
     *
     * Fail-closed: ein leerer oder fehlender Code trifft KEINE Zeile (kein Land hat
     * einen leeren Code) statt still alle — eine Route ohne `country` darf nicht
     * aussehen wie "alle Laender".
     *
     * Der Spaltenname wird qualifiziert, weil `regions` ebenfalls eine Spalte `code`
     * hat: in einer verjointen Abfrage waere `LOWER(code)` mehrdeutig.
     */
    public function scopeMatchingCode(Builder $query, ?string $code): Builder
    {
        return $query->whereRaw(
            'LOWER('.$query->qualifyColumn('code').') = ?',
            [mb_strtolower((string) $code)],
        );
    }

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
