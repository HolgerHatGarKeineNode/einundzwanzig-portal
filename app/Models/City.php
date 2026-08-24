<?php

namespace App\Models;

use Akuechler\Geoly;
use App\Http\Controllers\Api\BtcMapCommunityController;
use App\Models\Concerns\HasOsmReference;
use App\Models\Concerns\SetsCreatedBy;
use App\Observers\ApiChangeObserver;
use App\Policies\CityPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[ObservedBy([ApiChangeObserver::class])]
class City extends Model
{
    use Geoly;
    use HasFactory;
    use HasOsmReference;
    use HasSlug;
    use SetsCreatedBy;

    /**
     * Die Felder, die eine Stadt zu DIESER Stadt machen — geschuetzt durch
     * {@see CityPolicy::updateIdentity()} (Issue #30).
     *
     * Alles andere an einer Stadt ist Anreicherung: OSM-Referenz, Wikidata, Wikipedia,
     * Koordinaten. Die darf jeder angemeldete Nutzer pflegen, so wie das Portal es
     * immer erlaubt hat. Diese fuenf nicht:
     *
     *  - `name` ist global eindeutig und traegt den Slug, der nach dem Anlegen
     *    eingefroren ist — ein Rename bricht keine URL, aber er benennt einen
     *    Datensatz um, an dem fremde Meetups haengen.
     *  - `country_id` und `region_id` verorten die Stadt; das Land bestimmt zusaetzlich
     *    die Sprache im BTC-Map-Export.
     *  - `population` und `population_date` entscheiden dort zusammen mit
     *    `simplified_geojson` ueber die SICHTBARKEIT: fehlt eines der drei, faellt jedes
     *    Meetup dieser Stadt aus dem Export
     *    ({@see BtcMapCommunityController}). Ein geleertes
     *    Stichjahr laesst fremde Eintraege in einem Drittsystem verschwinden, ohne
     *    dass hier ein Fehler entsteht.
     *
     * Die Liste steht hier und nicht in einem der drei Schreibpfade, weil REST-API,
     * MCP-Tool und Portal-Formular dieselbe Antwort geben muessen. Eine Stadt, die je
     * nach Eingang anders geschuetzt ist, ist genau die Art Unterschied, die niemand
     * vermutet und jeder debuggt.
     *
     * @var array<int, string>
     */
    public const IDENTITY_FIELDS = [
        'name',
        'country_id',
        'region_id',
        'population',
        'population_date',
    ];

    /**
     * Welche Identitaetsfelder eine Eingabe anfassen WILL — und zwar nur die, die den
     * bestehenden Wert wirklich aendern.
     *
     * Der Unterschied zaehlt: ein Formular schickt immer alle Felder mit, auch
     * unveraenderte. Wer nur die OSM-Referenz ergaenzt, sendet `name` und `country_id`
     * unveraendert mit — das darf kein 403 ausloesen. Erst ein abweichender Wert ist
     * eine Identitaetsaenderung.
     *
     * @param  array<string, mixed>  $input
     * @return array<int, string>
     */
    public function identityChanges(array $input): array
    {
        return array_values(array_filter(
            self::IDENTITY_FIELDS,
            function (string $field) use ($input): bool {
                if (! array_key_exists($field, $input)) {
                    return false;
                }

                $new = $input[$field];
                $old = $this->getAttribute($field);

                if ($new === null || $old === null) {
                    return $new !== $old;
                }

                /*
                 * Ein Nicht-Skalar ist immer eine Aenderung — und zwar ohne ihn anzufassen.
                 * Diese Methode laeuft im REST-Pfad VOR der Validierung, gegen die rohe
                 * Eingabe: ein `name[]=x` im Request kaeme hier als Array an, und die
                 * Umwandlung darunter warf dafuer eine "Array to string conversion". Aus
                 * einer 422 wurde so eine 500 — kein Bypass, aber der falsche Fehler.
                 *
                 * Fail-closed statt uebersprungen: wer Unsinn in ein geschuetztes Feld
                 * schickt, faellt in die Berechtigungspruefung und danach in die
                 * Validierung. Waere es umgekehrt, koennte ein Typ, den dieser Vergleich
                 * nicht kennt, an der Pruefung vorbeilaufen.
                 */
                if (! is_scalar($new)) {
                    return true;
                }

                // Lose verglichen, weil dieselbe Zahl je nach Eingang als String
                // ankommt: "42" aus einem Formular und 42 aus JSON sind derselbe Wert.
                return (string) $new !== (string) $old;
            }
        ));
    }

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
        'osm_id' => 'integer',
        'osm_lat' => 'float',
        'osm_lon' => 'float',
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
            /*
             * Feste Sprache statt Cookie::get('lang'): die Transliteration haengt sonst
             * am Nutzer, der gerade speichert — Str::slug macht aus "Koeln" nur im
             * deutschen Modus "koeln", sonst "koln". Gemessen am 2026-08-22: 37 der 314
             * Meetups haetten sich allein dadurch verschoben. Ausserdem greift Cookie::get()
             * in Konsolenbefehlen und Jobs ins Leere.
             */
            ->usingLanguage(config('app.locale'))
            /*
             * Konsistenz mit den uebrigen Models; der City-Slug ist kein Route-Key, wandert aber sonst genauso.
             * Ohne diese Zeile erzeugt HasSlug den Slug bei JEDEM Update neu.
             */
            ->doNotGenerateSlugsOnUpdate();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Verwaltungsebene 1 (Bundesstaat/Bundesland/Provinz) — optional.
     *
     * Staedte in Laendern ohne gepflegte Regionen bleiben ohne Zuordnung; jede Abfrage
     * ohne Regionsfilter verhaelt sich unveraendert.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
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
