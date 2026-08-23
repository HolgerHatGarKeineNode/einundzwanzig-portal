<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexChangeRequest;
use App\Http\Resources\ApiChangeResource;
use App\Models\ApiChange;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

#[Group(name: 'Realtime', weight: 6)]
class ChangeController extends Controller
{
    /**
     * Der Log-Kanal des Aufruf-Zaehlers, siehe {@see self::countRequest()}.
     */
    private const COUNTER_CHANNEL = 'api-changes';

    /**
     * List changes since a cursor
     *
     * Public change log of the six API resources (meetup, meetup-event, city, course,
     * course-event, lecturer). Every entry carries the complete object, so nothing has
     * to be fetched afterwards — including deletions, which are invisible anywhere else
     * because no resource uses soft deletes.
     *
     * Unlike the other read endpoints, which return a bare array, this one wraps the
     * list in an object: the cursor has to travel with it. `changes` holds the entries
     * in ascending `sequence` order, `next_since` is the cursor to send on the next
     * call, and `has_more` says whether the page was cut off by `limit`. Poll until
     * `has_more` is false, then keep polling with the last `next_since`.
     *
     * Without `since` the newest `limit` entries are returned (`has_more` is then
     * false) so a new consumer can pick up a starting cursor without reading the whole
     * table.
     *
     * `cursor_expired` is the one field you must not ignore. The log is pruned after 30
     * days, so a cursor older than the oldest entry still on file cannot be resumed:
     * changes — deletions above all — happened that this endpoint can no longer hand
     * out. The field is `true` exactly then, and `false` in every other answer,
     * including when no cursor was sent at all, so it can be read without checking
     * whether it is present. On `true`, do a full read of the regular endpoints and
     * then continue from the `next_since` of this very answer. Without the field an
     * expired cursor would return an empty list — indistinguishable from "nothing
     * changed", and the consumer would keep a stale cache believing it is current.
     *
     * @return array{changes: list<array<string, mixed>>, next_since: int|null, has_more: bool, cursor_expired: bool}
     */
    public function index(IndexChangeRequest $request): array
    {
        /*
         * Abweichung von den Nachbar-Controllern, mit Absicht: die Query-Parameter
         * sind hier NICHT per #[QueryParameter] beschrieben, sondern als Kommentar
         * ueber der jeweiligen Regel in {@see IndexChangeRequest::rules()}. Scramble
         * leitet die Parameter aus dem Form Request ab, sobald es einen gibt, und
         * ueberschreibt die Attribute dabei stillschweigend — gemessen: mit beidem
         * zugleich stand `since` ohne Beschreibung im Dokument. Die Nachbarn haben
         * keinen Form Request, dort greifen die Attribute.
         */
        $limit = $request->limit();
        $sequence = $request->cursorSequence();
        $timestamp = $request->cursorTimestamp();
        $resources = $request->resourceFilter();

        $query = ApiChange::query()
            // Ausdrueckliche Spaltenliste statt select('*'): ausgegeben wird nur
            // `payload`, und `id` ist der Cursor. Die uebrigen Spalten sind
            // Filterkriterien und muessen dafuer nicht mitgelesen werden.
            ->select('id', 'payload')
            ->when(
                $resources !== [],
                fn (Builder $query) => $query->whereIn('resource', $resources),
            );

        if ($sequence !== null) {
            // Exklusiv: der Konsument hat die Zeile mit genau dieser Sequenz schon.
            $query->where('id', '>', $sequence);
        } elseif ($timestamp !== null) {
            $query->where('occurred_at', '>', $timestamp);
        }

        if ($sequence !== null || $timestamp !== null) {
            /*
             * limit + 1 gelesen, limit ausgegeben: `has_more` steht damit fest, ohne
             * eine zweite COUNT-Query ueber eine Tabelle, die im Betrieb waechst.
             */
            $rows = $query->orderBy('id')->limit($limit + 1)->get();
            $hasMore = $rows->count() > $limit;
            $rows = $rows->take($limit)->values();
        } else {
            /*
             * Ohne Cursor NICHT die ganze Tabelle: ein Neueinsteiger will nicht 30 Tage
             * Historie, er will einen Startpunkt. Also die juengsten `limit` Zeilen —
             * absteigend gelesen, aufsteigend ausgegeben, damit die Sortierung der
             * Antwort dieselbe ist wie in jedem anderen Fall. `has_more` ist hier
             * immer false: jenseits der juengsten Zeile gibt es nichts mehr, und was
             * davor liegt, holt der Aufrufer nicht mit demselben Vorwaertscursor.
             */
            $rows = $query->orderByDesc('id')->limit($limit)->get()->reverse()->values();
            $hasMore = false;
        }

        $this->countRequest($request, $rows->count());

        return [
            'changes' => ApiChangeResource::collection($rows)
                /*
                 * ->resolve() wie in den uebrigen Controllern: ::collection() haengt
                 * sonst einen "data"-Schluessel um die Liste, und der waere hier
                 * doppelt irrefuehrend — jeder EINTRAG traegt bereits ein eigenes
                 * `data`-Feld mit dem Objekt.
                 */
                ->resolve(),
            /*
             * Der Cursor fuer den naechsten Aufruf. Bei einer leeren Seite bleibt die
             * mitgeschickte Sequenz stehen, statt auf null zu fallen — sonst spulte
             * ein Konsument, der einmal nichts vorfindet, an den Anfang zurueck.
             */
            'next_since' => $rows->last()?->id ?? $sequence,
            'has_more' => $hasMore,
            'cursor_expired' => $this->cursorExpired($sequence, $timestamp),
        ];
    }

    /**
     * Der Aufruf-Zaehler (Plan-Schritt 8).
     *
     * Die nginx-Konfig der Produktions-Site hat `access_log off`; ohne diese Zeile
     * gibt es keine Spur eines Aufrufs, und P3 ("14 Tage messen, dann entscheiden, ob
     * Reverb gebaut wird") haette keine Zahl. Bewusst ein Log-Eintrag und KEIN
     * Datenbank-Schreibvorgang: der Endpunkt wird gepollt, ein INSERT je Poll erzeugte
     * genau die Schreiblast, die hier gemessen werden soll.
     *
     * Bewusst ein eigener Kanal mit fest verdrahtetem Level `info` statt des
     * Default-Stacks: steht `LOG_LEVEL` auf Produktion auf `error`, faellt ein
     * `Log::info` still aus — die Messung waere weg und ihr Fehlen unsichtbar.
     */
    private function countRequest(IndexChangeRequest $request, int $returned): void
    {
        Log::channel(self::COUNTER_CHANNEL)->info('api-changes.request', [
            'since' => $request->input('since'),
            'resource' => $request->resourceFilter(),
            'limit' => $request->limit(),
            'returned' => $returned,
            'client' => self::clientFingerprint($request->ip()),
            // Gekuerzt: der Wert dient dazu, Konsumenten auseinanderzuhalten, nicht
            // dazu, beliebig lange Header in die Logdatei zu schreiben. Anders als die
            // IP ist er keine Angabe ueber eine Person, sondern ueber ein Programm.
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 200),
        ]);
    }

    /**
     * Ein stabiles Pseudonym des Aufrufers — keine IP im Klartext.
     *
     * P3 fragt "wie viele verschiedene Konsumenten rufen ab und wie oft", nicht "wer".
     * Fuer die erste Frage genuegt ein Wert, der ueber Wochen derselbe bleibt und zwei
     * Aufrufer unterscheidbar macht; die zweite beantwortet diese Site bewusst nicht —
     * ihre nginx-Konfig hat `access_log off`, und ein eigener Zaehler waere ein
     * schlechter Anlass, das zurueckzunehmen.
     *
     * HMAC mit `app.key` und nicht ein blankes `hash()`: der IPv4-Raum hat rund vier
     * Milliarden Adressen und ist damit in Minuten durchprobiert. Ein ungeschluesselter
     * Hash waere ruecktauschbar und damit dieselbe Angabe in unleserlich. Auf 12
     * Zeichen gekuerzt: das unterscheidet Konsumenten (P3 erwartet eine einstellige
     * Zahl davon) und laesst nichts mehr rekonstruieren.
     */
    private static function clientFingerprint(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 12);
    }

    /**
     * Ist der mitgeschickte Cursor aelter als alles, was noch im Log steht?
     *
     * Dann fehlen dem Konsumenten Aenderungen, die der Prune-Lauf abgeraeumt hat, und
     * er MUSS das erfahren: ohne diese Antwort saehe der Fall aus wie "es hat sich
     * nichts geaendert" — und weil kein Model SoftDeletes nutzt, waeren gerade die
     * Loeschungen die Eintraege, von denen er nie erfaehrt.
     *
     * Bewusst KEIN 410: der Request ist nicht falsch, die Antwort ist unvollstaendig.
     * Ein Statuscode-Wechsel traefe bestehende Clients haerter als ein Feld, das sie
     * heute noch ignorieren duerfen.
     *
     * Die Grenze ist bewusst streng gezogen (`<` gegen das aelteste vorhandene
     * Element): ist die Zeile, deren Sequenz der Konsument mitschickt, selbst schon
     * geprunt, meldet das Feld `true`, auch wenn zwischen ihr und der aeltesten
     * verbliebenen Zeile zufaellig nichts lag. Der Preis dafuer ist ein
     * ueberfluessiger Vollabgleich, der Preis der umgekehrten Ungenauigkeit waere ein
     * stiller Datenverlust — und der ist die Fehlerklasse, gegen die dieses Feld
     * ueberhaupt gebaut ist.
     *
     * Die Aggregatabfrage laeuft nur, wenn ueberhaupt ein Cursor kam, und trifft den
     * Index auf `id` bzw. `occurred_at`. Sie ist NICHT nach `resource` gefiltert: der
     * Cursor ist global, und geprunt wird nach Alter — was vor dem aeltesten Eintrag
     * liegt, ist fuer jede Ressource weg.
     */
    private function cursorExpired(?int $sequence, ?CarbonInterface $timestamp): bool
    {
        if ($sequence !== null) {
            $oldest = ApiChange::query()->min('id');

            // Leeres Log: es ist nichts abgelaufen, es ist nur nichts da.
            return $oldest !== null && $sequence < (int) $oldest;
        }

        if ($timestamp !== null) {
            $oldest = ApiChange::query()->min('occurred_at');

            return $oldest !== null && $timestamp->lt(Carbon::parse($oldest));
        }

        return false;
    }
}
