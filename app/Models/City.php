<?php

namespace App\Models;

use Akuechler\Geoly;
use App\Http\Controllers\Api\BtcMapCommunityController;
use App\Models\Concerns\DescribesItselfForDisambiguation;
use App\Models\Concerns\HasOsmReference;
use App\Models\Concerns\SetsCreatedBy;
use App\Observers\ApiChangeObserver;
use App\Policies\CityPolicy;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[ObservedBy([ApiChangeObserver::class])]
class City extends Model implements DescribesItselfForDisambiguation
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
    /**
     * Steuerfeld, keine Spalte: die ausdrueckliche Bestaetigung, dass hier bewusst ein
     * weiterer Ort gleichen Namens entsteht.
     *
     * Es reist mit den validierten Attributen bis hierher und muss vor dem create()
     * heraus — sonst versucht Eloquent, es zu speichern, und die Tabelle kennt es nicht.
     */
    public const CONFIRM_DUPLICATE = 'confirm_duplicate';

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
     * Alle Staedte, deren Name (normalisiert) und Land zu den Angaben passen.
     *
     * Normalisiert heisst: klein und ohne aeussere Leerzeichen. Beides ist noetig, weil
     * der Bestand beides enthaelt — 12 der 305 Namen in Produktion tragen ein
     * nachgestelltes Leerzeichen, und ein exakter Vergleich haelt `'Offenburg '` fuer
     * eine andere Stadt als `'Offenburg'`.
     *
     * Gibt eine Collection zurueck, nicht ein Modell: ein Ortsname ist nicht eindeutig,
     * und wer hier ein einzelnes Ergebnis erwartet, hat schon den Fehler gemacht, den
     * diese Methode verhindern soll.
     *
     * @return EloquentCollection<int, self>
     */
    public static function matchingName(string $name, int|string|null $countryId = null): EloquentCollection
    {
        return static::query()
            /*
             * TRIM(), nicht BTRIM(): beide tun dasselbe, aber BTRIM kennt nur Postgres.
             * Die Tests laufen auf SQLite (phpunit.xml), Produktion auf PG 18.4 — eine
             * Funktion, die nur eine der beiden kennt, macht aus jedem Testlauf einen
             * Fehlalarm oder, schlimmer, aus einem gruenen Lauf eine falsche Zusage.
             * TRIM(x) ist SQL-Standard und in beiden vorhanden.
             */
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))])
            ->when($countryId !== null, fn ($query) => $query->where('country_id', $countryId))
            ->orderBy('id')
            ->get();
    }

    /**
     * Loest eine Stadt eindeutig auf oder legt sie an — und scheitert sichtbar, wenn
     * beides nicht geht.
     *
     * ## Warum das keine "createOrFind"-Methode mehr ist
     *
     * Bis 2026-08-25 hiess sie `createOrFindByName()` und suchte allein auf dem Namen,
     * obwohl der Aufrufer `country_id` mitgeschickt hatte. Sie warf es weg und
     * antwortete mit 200. Wer "Springfield" fuer Missouri anlegte, waehrend Springfield
     * (Illinois) existierte, bekam den Illinois-Datensatz — und das Meetup hing still an
     * der falschen Stadt (Issue #33).
     *
     * Der Fehler war nicht der fehlende Index, sondern die Haltung: eine Methode, die
     * bei Mehrdeutigkeit den ersten Treffer nimmt, ist auch mit perfektem Schema falsch.
     * Ein Ortsname ist auf keiner Verwaltungsebene eindeutig — acht Neuenkirchen in
     * Niedersachsen, zwei davon im selben Landkreis.
     *
     * ## Die Reihenfolge
     *
     * 1. **OSM-Referenz** (`osm_type` + `osm_id`) mitgeschickt? Dann ist die Sache
     *    exakt entschieden — Treffer oder Neuanlage, keine Rueckfrage.
     * 2. Sonst: Name + Land. Genau ein Treffer gewinnt, kein Treffer fuehrt zur
     *    Neuanlage.
     * 3. **Mehrere Treffer** → ValidationException mit den Kandidaten. Nie "nimm den
     *    ersten".
     * 4. **Neuanlage neben einem gleichnamigen Ort ohne Identifier** → ebenfalls
     *    ValidationException. Ein zweites "Georgetown" im selben Land ist erlaubt, aber
     *    es ist eine Entscheidung und kein Nebeneffekt.
     *
     * `region_id` spielt bewusst KEINE Rolle: sie ist ein Merkmal fuer die Laender, in
     * denen wir Regionen wollen (heute DE und US, 5 von 305 Zeilen). Wuerde sie hier
     * mitentscheiden, verhielte sich das Portal in Laendern mit Regionen anders als in
     * Laendern ohne — und der Unterschied fiele erst dem auf, den er trifft.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException wenn der Name mehrdeutig ist oder eine Neuanlage
     *                             neben einem gleichnamigen Ort nicht bestaetigt wurde
     */
    public static function resolveOrCreate(array $attributes): self
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        $countryId = $attributes['country_id'] ?? null;

        /*
         * Eine mitgeschickte OSM-Referenz beendet die Frage in BEIDE Richtungen: traegt
         * sie schon eine Stadt, ist es diese; traegt sie noch keine, ist es eine neue.
         * Der Name spielt dann keine Rolle mehr — auch nicht, wenn er mehrdeutig ist.
         * Genau dafuer ist ein Identifier da.
         *
         * Ohne den zweiten Zweig waere die Referenz nur halb wirksam: wer das neunte
         * Neuenkirchen mit seiner OSM-Relation anlegt, bekaeme trotzdem die
         * Mehrdeutigkeitsmeldung ueber die acht anderen — und haette keinen Weg mehr,
         * eindeutig zu sein, obwohl er das Eindeutigste mitgeschickt hat, was es gibt.
         */
        if (static::hasOsmReference($attributes)) {
            return static::matchingOsmReference($attributes)
                ?? static::create(Arr::except($attributes, [self::CONFIRM_DUPLICATE]));
        }

        /*
         * Die Bestaetigung ueberspringt die Bestandssuche — und zwar bewusst vor jeder
         * Namenspruefung. Ohne diese Reihenfolge waere sie wirkungslos: wer ein ZWEITES
         * Georgetown im selben Land anlegen will, findet mit der Namenssuche immer das
         * erste und bekaeme es zurueck, statt ein neues zu bekommen. Genau der Fall, den
         * die Bestaetigung ausdruecken soll, waere dann der einzige, den sie nicht kann.
         *
         * Die OSM-Referenz steht davor: dieselbe OSM-ID ist derselbe Ort, und daran
         * aendert auch eine Bestaetigung nichts.
         */
        if (static::isConfirmed($attributes)) {
            return static::create(Arr::except($attributes, [self::CONFIRM_DUPLICATE]));
        }

        $candidates = static::matchingName($name, $countryId);

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($candidates->count() > 1) {
            throw ValidationException::withMessages([
                'name' => __(':name ist in diesem Land mehrdeutig — :count Orte tragen den Namen. Waehle einen davon ueber seine id, oder schicke osm_type und osm_id mit, um eindeutig zu sein. Kandidaten: :list', [
                    'name' => $name,
                    'count' => $candidates->count(),
                    'list' => static::describeCandidates($candidates),
                ]),
            ]);
        }

        if (static::matchingName($name)->isNotEmpty() && ! static::hasIdentifier($attributes)) {
            throw ValidationException::withMessages([
                'name' => __('Es gibt bereits mindestens einen Ort namens :name. Das ist kein Widerspruch — gleichnamige Orte existieren wirklich. Schicke osm_type und osm_id mit, um diesen Ort eindeutig zu machen, oder setze confirm_duplicate, wenn es bewusst ein weiterer Ort gleichen Namens sein soll. Vorhanden: :list', [
                    'name' => $name,
                    'list' => static::describeCandidates(static::matchingName($name)),
                ]),
            ]);
        }

        return static::create(Arr::except($attributes, [self::CONFIRM_DUPLICATE]));
    }

    /**
     * Der exakte Treffer ueber die OSM-Referenz, wenn eine mitgeschickt wurde.
     *
     * Gibt null zurueck, wenn keine Referenz dabei ist ODER wenn sie noch zu keiner
     * Stadt gehoert. Der Aufrufer unterscheidet die beiden Faelle selbst — er weiss
     * schon, ob eine Referenz dabei war, und die zwei Zustaende fuehren dort zu
     * verschiedenen Schritten (Namenssuche gegen Neuanlage).
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function matchingOsmReference(array $attributes): ?self
    {
        if (! static::hasOsmReference($attributes)) {
            return null;
        }

        return static::query()
            ->where('osm_type', $attributes['osm_type'])
            ->where('osm_id', $attributes['osm_id'])
            ->first();
    }

    /**
     * Traegt dieser Anlageversuch etwas, das ihn von einem gleichnamigen Ort abhebt?
     *
     * Entweder eine OSM-Referenz (macht ihn exakt) oder eine ausdrueckliche Bestaetigung
     * (macht ihn bewusst). Beides ist gueltig; nichts davon ist es nicht.
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function hasIdentifier(array $attributes): bool
    {
        return static::hasOsmReference($attributes) || static::isConfirmed($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function isConfirmed(array $attributes): bool
    {
        return filter_var($attributes[self::CONFIRM_DUPLICATE] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function hasOsmReference(array $attributes): bool
    {
        return ! blank($attributes['osm_type'] ?? null) && ! blank($attributes['osm_id'] ?? null);
    }

    /**
     * Kandidaten so beschreiben, dass man sie auseinanderhalten kann.
     *
     * Der Name allein hilft hier nicht — bei acht Neuenkirchen stuende er achtmal da.
     * Also id und Koordinaten, und die Region nur, wenn es eine gibt: sie ist Beiwerk,
     * kein Kriterium, und in Laendern ohne Regionen waere ein leeres Feld nur Rauschen.
     *
     * @param  Collection<int, self>  $candidates
     */
    private static function describeCandidates(Collection $candidates): string
    {
        return $candidates
            ->take(10)
            ->map(fn (self $city): string => $city->disambiguationLabel())
            ->join('; ');
    }

    /**
     * Wie diese Stadt sich von einer gleichnamigen unterscheidet: id, Region, Koordinaten.
     *
     * Die Region steht nur da, wenn es eine gibt. Ein leeres Feld waere in einem Land
     * ohne Regionen — und das sind die meisten — nur Rauschen; die Koordinaten
     * unterscheiden ohnehin immer, weil `latitude`/`longitude` NOT NULL sind.
     */
    public function disambiguationLabel(): string
    {
        $teile = ['#'.$this->getKey()];

        $this->loadMissing('region');

        if ($this->region?->code) {
            $teile[] = mb_strtoupper($this->region->code);
        }

        $teile[] = number_format((float) $this->latitude, 4).'/'.number_format((float) $this->longitude, 4);

        return implode(' ', $teile);
    }

    /**
     * Get the options for generating the slug.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            /*
             * Die Region kommt in den Slug, wo es eine gibt.
             *
             * Seit der Namens-Unique gefallen ist (Issue #33), koennen zwei Staedte
             * gleichen Namens im selben Land stehen — und Spatie haengt dann still eine
             * Zaehlnummer an: `us-springfield`, `us-springfield-1`, `us-springfield-2`.
             * Die Nummer ist eindeutig und sagt nichts. Mit der Region wird daraus
             * `us-il-springfield` und `us-mo-springfield`.
             *
             * Bleibt die Region leer — was fuer 300 der 305 Staedte gilt und fuer alle
             * Laender ohne Regionen dauerhaft so bleibt —, entsteht exakt derselbe Slug
             * wie bisher. Das ist die Bedingung, unter der diese Aenderung ueberhaupt
             * vertretbar ist: sie verschiebt keinen einzigen bestehenden Wert.
             *
             * Zwei gleichnamige Orte in DERSELBEN Region bekommen weiterhin eine
             * Zaehlnummer. Das ist selten (im gemessenen Bestand kein einziger Fall) und
             * korrekt — mehr Unterscheidung gibt die Verwaltungsebene nicht her.
             */
            ->generateSlugsFrom(fn (self $city): string => collect([
                $city->country?->code,
                $city->region?->code,
                $city->name,
            ])->filter()->join(' '))
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
