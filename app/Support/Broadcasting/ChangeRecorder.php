<?php

namespace App\Support\Broadcasting;

use App\Events\ResourceChanged;
use App\Http\Resources\CityResource;
use App\Http\Resources\CourseEventResource;
use App\Http\Resources\CourseResource;
use App\Http\Resources\LecturerResource;
use App\Http\Resources\MeetupEventResource;
use App\Http\Resources\MeetupResource;
use App\Models\ApiChange;
use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Die einzige Stelle, an der ein Aenderungs-Payload entsteht (Issue #29).
 *
 * Der Recorder laeuft SYNCHRON im Observer, im selben Request wie das Schreiben. Das
 * ist keine Bequemlichkeit, sondern die Bedingung dafuer, dass der Vertrag haelt:
 *
 *  - Ein `deleted` traegt ein Array, niemals ein Model. Laravels Broadcast-Job setzt
 *    `deleteWhenMissingModels = true`; ein Event, das ein geloeschtes Model in die
 *    Queue traegt, wird beim Ausfuehren still verworfen — kein Fehler, kein
 *    `failed_jobs`-Eintrag. Kein Model nutzt SoftDeletes, der Datensatz ist wirklich weg.
 *  - Ein `updated`-Payload ist der Stand ZUM ZEITPUNKT der Aenderung, nicht der Stand
 *    zur Sendezeit. Ein Job, der das Model nachlaedt, liefert bei zehn parallelen
 *    Horizon-Prozessen Payloads in falscher Reihenfolge.
 *  - `sequence` steht fest, bevor irgendetwas dispatcht wird.
 *
 * {@see self::record()} schreibt die Zeile und dispatcht danach
 * {@see ResourceChanged} mit {@see ApiChange::broadcastPayload()} — dem gekuerzten
 * Versand-Umschlag. Der Recorder kennt dabei keine Kanaele: welcher Kanal welches
 * Ereignis traegt, entscheidet das Event.
 */
class ChangeRecorder
{
    /**
     * Reverb weist Requests ueber `max_request_size` (Default 10 000 Bytes) mit HTTP 413
     * ab. Ueber der Grenze geht das Event ohne `data` raus; die `api_changes`-Zeile
     * behaelt das vollstaendige Objekt und der Konsument holt es ueber /api/changes.
     */
    public const MAX_BROADCAST_BYTES = 10000;

    /**
     * DIE KANONISCHE RELATIONSGESTALT — der Vertrag zum externen Konsumenten.
     *
     * Eine `JsonResource` hat in diesem Projekt mehrere Gestalten: `MeetupEventResource`
     * liefert `tags` nur bei geladener Relation, `MeetupResource` liefert `is_leader` nur
     * bei geladenem `meetup_user`-Pivot. Welche Gestalt im Log landet, ist deshalb eine
     * Entscheidung und keine Nebenwirkung — ohne sie ist "das Payload ist gleich der
     * Resource" nicht pruefbar, weil beide Seiten wahr waeren.
     *
     * Die Regeln, nach denen `relations` gefuellt ist:
     *
     *  - `tags` JA. Sie gehoeren fachlich zum Termin, sind oeffentlich, und ein
     *    Konsument, der sie aus dem Payload nicht sieht, muesste jede Aenderung
     *    nachladen — genau das soll das Log ersparen.
     *  - Nachbar-Objekte (`course`, `city` am CourseEvent) JA. Sie liefern nur `id` und
     *    `name` und stehen so auch in `GET /api/course-events`.
     *  - `media` JA, obwohl es die Ausgabe nicht veraendert: `getFirstMediaUrl()` laedt
     *    die Relation sonst lazy nach, einmal pro Schreibvorgang.
     *  - Pivot- und Auth-abhaengige Felder NEIN. `is_leader` beantwortet die Frage "darf
     *    DIESER Token-Inhaber das bearbeiten" — auf einem oeffentlichen Kanal gibt es
     *    kein "dieser", die Antwort waere sinnlos und irrefuehrend zugleich.
     *  - `country` am City NEIN. `CityResource` gibt es nicht aus; die Relation nur zu
     *    laden, um `api_changes.country_code` zu fuellen, waere eine zusaetzliche Query
     *    pro Schreibvorgang fuer eine Spalte, die erst P7 liest.
     *
     * `self_route` ist der Name der `show`-Route, falls es sie gibt. Heute haben nur
     * `courses` und `lecturers` eine — fuer die uebrigen vier ist `links.self` null,
     * statt eine URL zuzusagen, die 404 liefert. Sobald P2 /api/changes und spaeter die
     * fehlenden show-Endpunkte liefert, gehoert der Routenname hier hinein.
     *
     * `previous` sind die Identifikatoren, die ein `deleted` mitgibt: der Schluessel zum
     * gezielten Cache-Invalidieren, wenn das Objekt selbst nicht mehr existiert.
     * `courses` hat keine Slug-Spalte, `meetup_events` auch nicht.
     *
     * @var array<class-string<Model>, array{
     *     name: string,
     *     resource: class-string<JsonResource>,
     *     relations: array<int, string>,
     *     self_route: string|null,
     *     previous: array<int, string>,
     *     city_id: string|null,
     * }>
     */
    private const RESOURCES = [
        Meetup::class => [
            'name' => 'meetup',
            'resource' => MeetupResource::class,
            'relations' => ['media'],
            'self_route' => null,
            'previous' => ['slug', 'city_id'],
            'city_id' => 'city_id',
        ],
        MeetupEvent::class => [
            'name' => 'meetup-event',
            'resource' => MeetupEventResource::class,
            'relations' => ['tags'],
            'self_route' => null,
            'previous' => ['meetup_id'],
            'city_id' => null,
        ],
        City::class => [
            'name' => 'city',
            'resource' => CityResource::class,
            'relations' => [],
            'self_route' => null,
            'previous' => ['slug', 'country_id'],
            'city_id' => 'id',
        ],
        Course::class => [
            'name' => 'course',
            'resource' => CourseResource::class,
            'relations' => ['media'],
            'self_route' => 'api.courses.show',
            'previous' => ['lecturer_id'],
            'city_id' => null,
        ],
        CourseEvent::class => [
            'name' => 'course-event',
            'resource' => CourseEventResource::class,
            'relations' => ['tags', 'course', 'city'],
            'self_route' => null,
            'previous' => ['course_id', 'city_id'],
            'city_id' => 'city_id',
        ],
        Lecturer::class => [
            'name' => 'lecturer',
            'resource' => LecturerResource::class,
            'relations' => ['media'],
            'self_route' => 'api.lecturers.show',
            'previous' => ['slug'],
            'city_id' => null,
        ],
    ];

    /**
     * Blockweise Stummschaltung fuer Seeder und Import-Commands, siehe {@see self::muted()}.
     */
    private static bool $muted = false;

    /**
     * Zeichnet eine Aenderung auf und gibt die geschriebene Zeile zurueck.
     *
     * Gibt null zurueck, wenn das Log aus ist, der Block stummgeschaltet ist oder das
     * Model gar keine API-Ressource hat.
     *
     * @param  'created'|'updated'|'deleted'  $action
     */
    public static function record(Model $model, string $action): ?ApiChange
    {
        if (! self::enabled()) {
            return null;
        }

        $definition = self::RESOURCES[$model::class] ?? null;

        if ($definition === null) {
            return null;
        }

        $occurredAt = now();

        $envelope = [
            'action' => $action,
            'resource' => $definition['name'],
            'id' => (int) $model->getKey(),
            // Wird nach dem Insert nachgetragen — die Sequenz IST die Zeilen-ID.
            'sequence' => null,
            'occurred_at' => $occurredAt->toIso8601String(),
            'api_version' => (string) config('scramble.info.version'),
            'data' => $action === 'deleted' ? null : self::data($model, $definition),
            'links' => ['self' => self::selfLink($model, $definition)],
        ];

        if ($action === 'deleted') {
            $envelope['previous'] = self::previous($model, $definition);
        }

        $change = ApiChange::create([
            'resource' => $definition['name'],
            'resource_id' => (int) $model->getKey(),
            'action' => $action,
            'country_code' => self::countryCode($model),
            'city_id' => self::cityId($model, $definition),
            'payload' => $envelope,
            'occurred_at' => $occurredAt,
        ]);

        /*
         * Zweiter Schreibvorgang, mit Absicht: `sequence` ist die Zeilen-ID und steht
         * erst nach dem Insert fest. Die Alternative waere, sie beim Lesen aus `id`
         * einzusetzen — dann laege im Payload-Feld etwas anderes als das, was der
         * Konsument bekommt, und ein Blick per SQL in die Spalte wuerde ueber den
         * Vertrag luegen. Ein UPDATE auf eine gerade geschriebene Zeile ist billiger
         * als eine Tabelle, deren Inhalt man nicht beim Wort nehmen kann.
         */
        $envelope['sequence'] = $change->id;
        $change->forceFill(['payload' => $envelope])->save();

        /*
         * Erst jetzt der Broadcast (P4), nach dem Schreiben der Zeile und nicht davor —
         * ein fehlgeschlagener Broadcast darf das Log nicht mitreissen, und mit
         * `'tries' => 1` (config/horizon.php:225) ist die Zeile das einzige Netz
         * darunter. Ueber die Leitung geht die gekuerzte Variante: Reverb weist alles
         * ueber `max_request_size` mit HTTP 413 ab, die Zeile behaelt das volle Objekt.
         *
         * Der Kill-Switch oben deckt das mit ab: wer nicht aufzeichnet, sendet auch
         * nicht — sonst gaebe es ein Ereignis auf dem Kanal, zu dem kein Resync-Eintrag
         * existiert, und ein Konsument, der es verpasst, erfuehre nie davon.
         */
        try {
            event(new ResourceChanged($change->broadcastPayload()));
        } catch (Throwable $e) {
            /*
             * "Darf das Log nicht mitreissen" ist woertlich gemeint, und es geht nicht
             * nur um das Log: der Recorder laeuft im Observer, also mitten im
             * Schreib-Request. Eine Ausnahme von hier schluege durch `save()` bis in
             * den Controller durch — und liefe der Write in einer Transaktion, risse sie den
             * fachlichen Datensatz gleich mit zurueck. Ein nicht erreichbarer Reverb
             * (oder eine volle Queue) wuerde damit jedes Speichern im Portal
             * verhindern. Das waere ein schlechterer Zustand als vor P4.
             *
             * Verloren ist dabei nichts, was nicht wiederholbar waere: die
             * `api_changes`-Zeile steht bereits, `/api/changes` liefert sie aus, und
             * genau dafuer ist sie da. Still ist es trotzdem nicht — der Log-Eintrag
             * nennt die Sequenz, ueber die sich der Ausfall nachvollziehen laesst.
             */
            Log::warning('Broadcast der Aenderung fehlgeschlagen', [
                'sequence' => $change->id,
                'resource' => $change->resource,
                'action' => $change->action,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return $change;
    }

    /**
     * Fuehrt einen Block aus, ohne Aenderungen aufzuzeichnen.
     *
     * Fuer Seeder und Import-Commands: ein `DatabaseSeeder`-Lauf erzeugt ueber 250
     * Datensaetze, die niemand als "Aenderung" abonniert hat. `finally` stellt den
     * vorherigen Zustand auch dann wieder her, wenn der Block wirft — sonst bliebe das
     * Log nach einem abgebrochenen Import bis zum Prozessende stumm.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public static function muted(Closure $callback): mixed
    {
        $previous = self::$muted;
        self::$muted = true;

        try {
            return $callback();
        } finally {
            self::$muted = $previous;
        }
    }

    /**
     * Ist das Aufzeichnen gerade scharf?
     */
    public static function enabled(): bool
    {
        return ! self::$muted && (bool) config('einundzwanzig.change_log.enabled', false);
    }

    /**
     * Die Ressourcen-Namen, die im Aenderungs-Log ueberhaupt vorkommen koennen.
     *
     * Additiv fuer P2: `GET /api/changes?resource=` weist einen unbekannten Wert mit
     * 422 ab statt mit einer leeren Liste. Die Liste dafuer wird hier gelesen und
     * NICHT im Validator noch einmal hingeschrieben — sonst kaeme eine siebte
     * Ressource im Log an, die der Endpunkt als Tippfehler zurueckweist, und der
     * Fehler waere von aussen nicht von "es ist nichts passiert" zu unterscheiden.
     *
     * @return array<int, string>
     */
    public static function resourceNames(): array
    {
        return array_values(array_column(self::RESOURCES, 'name'));
    }

    /**
     * Das Payload in der Gestalt, in der es ueber einen WebSocket gehen darf.
     *
     * Ueber {@see self::MAX_BROADCAST_BYTES} faellt `data` weg und `truncated` kommt
     * dazu. Gemessen wird das VOLLSTAENDIGE Envelope, nicht nur `data` — Reverb misst
     * den ganzen Request.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function broadcastPayload(array $payload): array
    {
        $encoded = json_encode($payload);

        if ($encoded !== false && strlen($encoded) <= self::MAX_BROADCAST_BYTES) {
            return $payload;
        }

        return [
            ...$payload,
            'data' => null,
            'truncated' => true,
        ];
    }

    /**
     * Das Resource-Ergebnis als nacktes Array, ohne `data`-Wrapper.
     *
     * `resolve()` filtert die `MissingValue`-Platzhalter heraus, die `whenLoaded()` und
     * `whenPivotLoaded()` hinterlassen. Der json-Roundtrip danach ist kein Umweg: er
     * loest verschachtelte Resource-Collections und Carbon-Instanzen genau so auf, wie
     * es die HTTP-Antwort tut. Ohne ihn laege in `payload` ein PHP-Objektgraph, der beim
     * Serialisieren nochmal anders aussehen koennte als das, was der Konsument sieht.
     *
     * Gelesen wird ueber `fresh()`, nicht vom beobachteten Model. Das kostet eine Query
     * und kauft dafuer zwei Dinge: die Relationen landen nicht ungefragt am Model des
     * Aufrufers, und die Zeitstempel stimmen. Ein `created_at`, das Laravel gerade in
     * Erinnerung hat, traegt Mikrosekunden; die Spalte in der Datenbank tut es nicht.
     * Ohne `fresh()` haette dasselbe Objekt im Log eine andere Uhrzeit als in
     * `GET /api/…` — genau die Sorte Abweichung, die ein Konsument als Aenderung liest.
     * Alle Schreib-Endpunkte unter `app/Http/Controllers/Api/` antworten aus demselben
     * Grund mit `->fresh()`.
     *
     * @param  array{relations: array<int, string>, resource: class-string<JsonResource>, ...}  $definition
     * @return array<string, mixed>
     */
    private static function data(Model $model, array $definition): array
    {
        // Fallback auf eine Kopie, falls die Zeile zwischen Event und Lesen verschwunden
        // ist. `load()` auf dem beobachteten Model selbst waere ein Nebeneffekt beim
        // Aufrufer.
        $subject = $model->fresh($definition['relations']) ?? (clone $model)->load($definition['relations']);

        /** @var JsonResource $resource */
        $resource = $definition['resource']::make($subject);

        return json_decode(json_encode($resource->resolve(request())) ?: '{}', true);
    }

    /**
     * Die letzten bekannten Identifikatoren eines geloeschten Datensatzes.
     *
     * @param  array{previous: array<int, string>, ...}  $definition
     * @return array<string, mixed>
     */
    private static function previous(Model $model, array $definition): array
    {
        $previous = [];

        foreach ($definition['previous'] as $attribute) {
            $previous[$attribute] = $model->getAttribute($attribute);
        }

        return $previous;
    }

    /**
     * @param  array{self_route: string|null, ...}  $definition
     */
    private static function selfLink(Model $model, array $definition): ?string
    {
        if ($definition['self_route'] === null || ! Route::has($definition['self_route'])) {
            return null;
        }

        return route($definition['self_route'], $model->getKey());
    }

    /**
     * @param  array{city_id: string|null, ...}  $definition
     */
    private static function cityId(Model $model, array $definition): ?int
    {
        if ($definition['city_id'] === null) {
            return null;
        }

        $value = $model->getAttribute($definition['city_id']);

        return $value === null ? null : (int) $value;
    }

    /**
     * Der Laendercode, aber nur wenn er ohne eine zusaetzliche Query zu haben ist.
     *
     * Heute heisst das: praktisch nie. Erst wenn P7 die Geo-Kanaele baut und dafuer
     * ohnehin `city.country` eager laedt, faellt der Wert nebenbei ab. Bis dahin ist
     * null die ehrliche Antwort — eine Spalte zu fuellen, die niemand liest, ist keine
     * Extra-Query je Schreibvorgang wert.
     */
    private static function countryCode(Model $model): ?string
    {
        if ($model->relationLoaded('country')) {
            return $model->getRelation('country')?->code;
        }

        if ($model->relationLoaded('city')) {
            $city = $model->getRelation('city');

            if ($city !== null && $city->relationLoaded('country')) {
                return $city->getRelation('country')?->code;
            }
        }

        return null;
    }
}
