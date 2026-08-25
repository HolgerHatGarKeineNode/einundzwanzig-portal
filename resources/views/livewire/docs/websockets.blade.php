<?php

use App\Attributes\SeoDataAttribute;
use App\Support\Broadcasting\ChangeRecorder;
use App\Traits\SeoTrait;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Consumer documentation for the realtime change feed (Issue #29, plan phase P6).
 *
 * DIESE SEITE IST AUF ENGLISCH UND WIRD NICHT UEBERSETZT — anders als /ki-assistent,
 * das dieselbe Kopfleiste traegt. Der Grund ist das Publikum, nicht die Bequemlichkeit:
 * die Nachbarseite dieser hier ist /docs/api, die Scramble-Referenz. Deren
 * `info.description` ist englisch, und Scalar ist in `config/scramble.php` fest auf
 * `locale => 'en'` genagelt, mit der dort notierten Begruendung, dass eine zweite
 * Sprache ein zweites OpenAPI-Dokument bedeutete. Beide Seiten beschreiben denselben
 * Vertrag mit denselben Feldnamen; stuenden die Feldnamen hier in neun Sprachen
 * gerahmt, waere der Vergleich schwerer, nicht leichter. /ki-assistent richtet sich an
 * Meetup-Organisatoren ohne Technikwissen — ein anderes Publikum, deshalb dort
 * neun Sprachen und hier eine.
 */
new
#[Layout('components.layouts.auth')]
#[SeoDataAttribute(key: 'docs_websockets')]
class extends Component {
    use SeoTrait;

    /**
     * Platzhalter fuer eine Verbindungsangabe, die es in dieser Umgebung nicht gibt.
     *
     * In Produktion greift er nicht mehr — P5 ist gefahren, der Key steht (gemessen am
     * 2026-08-24: `/docs/websockets` zeigt dort eine vollstaendige `wss://`-URL auf
     * `ws.portal.einundzwanzig.space`). Lokal, in Tests und auf jeder Umgebung ohne
     * gesetztes `REVERB_APP_KEY` greift er weiterhin, und genau dafuer ist er da.
     *
     * Warum ueberhaupt ein Platzhalter statt eines erfundenen Werts: ein falscher Key
     * verbindet nicht, und der Konsument sucht den Fehler dann bei sich. Der Platzhalter
     * traegt den Namen der Env-Variablen, aus der der Wert kommt — damit erkennbar ist,
     * was fehlt, statt dass es nach einem Defekt aussieht.
     *
     * Der Key ist der einzige Wert, der einen Platzhalter braucht. Host, Schema und Port
     * lassen sich aus der Konfiguration ableiten, und ein leerer Pfad ist seit P5 der
     * Normalfall statt eines Fehlens — siehe connection() weiter unten.
     */
    private const KEY_PLACEHOLDER = '{REVERB_APP_KEY}';

    /**
     * Fliesstext mit `Backticks` in Fliesstext mit <code>-Auszeichnung.
     *
     * Erst escapen, dann ersetzen — in dieser Reihenfolge, sonst waere die Methode ein
     * Weg, beliebiges HTML in die Seite zu schreiben. Die Texte stammen zwar
     * ausschliesslich aus dieser Datei, aber eine Funktion, die nur sicher ist, solange
     * niemand sie woanders benutzt, ist keine sichere Funktion.
     */
    private function formatted(string $text): HtmlString
    {
        return new HtmlString(preg_replace(
            '/`([^`]+)`/',
            '<code class="rounded bg-zinc-900/5 px-1 py-0.5 font-mono text-[0.9em] dark:bg-white/10">$1</code>',
            e($text),
        ));
    }

    /**
     * Die Verbindungsangaben, vollstaendig aus der Konfiguration gelesen.
     *
     * Nichts davon ist hier fest verdrahtet: aendert sich der Betrieb, aendert sich
     * die Seite mit — genau das war der Punkt, und P5 hat ihn eingeloest.
     *
     * DREI QUELLEN, WEIL ES DREI VERSCHIEDENE DINGE SIND:
     *
     *  1. HOST — `einundzwanzig.realtime.public_host` (`REVERB_PUBLIC_HOST`), mit
     *     `app.url` als Rueckfall. NICHT
     *     `broadcasting.connections.reverb.options.host`: dort steht das
     *     PUBLISH-Ziel, in Produktion `127.0.0.1:8080`, damit ein Broadcast nicht als
     *     TLS-Roundtrip nach draussen und wieder herein laeuft. Und seit P5 auch
     *     nicht mehr `app.url` allein: Reverb haengt nicht als Pfad-Proxy unter der
     *     Portal-Domain, sondern laeuft ueber Forges Reverb-Integration auf einer
     *     eigenen Subdomain mit eigenem Zertifikat. Lokal, wo beides zusammenfaellt,
     *     bleibt der Rueckfall auf `app.url` richtig.
     *  2. SCHEMA und PORT — aus `app.url`. Das Schema, weil `wss` genau dann gilt,
     *     wenn das Portal selbst per TLS laeuft. Der Port nur dann, wenn auch der
     *     Host von dort kommt: einen abweichenden Port an einen ANDEREN Hostnamen zu
     *     kleben, ergaebe lokal `wss://ws.example.test:8000` — eine Adresse, die es
     *     nirgends gibt. Steht der Port am konfigurierten Host (`host:8443`), wird er
     *     von dort gelesen.
     *  3. PFAD und KEY — aus den Reverb-Configs. Der Pfad kommt aus
     *     `reverb.servers.reverb.path` (dem `--path` des Daemons), mit
     *     `broadcasting.connections.reverb.options.path` als zweiter Quelle, weil
     *     beide dieselbe Env-Variable lesen. LEER IST DER NORMALFALL: auf einer
     *     eigenen Subdomain steht der Handshake unter dem Standardpfad `/app`, und
     *     dann darf in der URL kein leeres Segment und erst recht kein Platzhalter
     *     stehen. Nur der Key hat einen Platzhalter — er ist der einzige Wert, den
     *     man nicht raten und nicht weglassen kann.
     *
     * @return array{scheme:string, host:string, port:int, has_path:bool, ws_path:string, key:string, url:string, published:bool}
     */
    #[Computed]
    public function connection(): array
    {
        $appUrl = (string) config('app.url');
        $scheme = parse_url($appUrl, PHP_URL_SCHEME) === 'http' ? 'ws' : 'wss';

        $publicHost = trim((string) config('einundzwanzig.realtime.public_host'), " \t\n\r\0\x0B/");

        if ($publicHost !== '') {
            // Ein optionaler Port darf am konfigurierten Host haengen.
            [$host, $port] = array_pad(explode(':', $publicHost, 2), 2, null);
        } else {
            $host = parse_url($appUrl, PHP_URL_HOST) ?: request()->getHost();
            $port = parse_url($appUrl, PHP_URL_PORT);
        }

        $path = trim((string) (
            config('reverb.servers.reverb.path')
            ?: config('broadcasting.connections.reverb.options.path')
        ), '/');

        $key = trim((string) config('broadcasting.connections.reverb.key'));

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => (int) ($port ?: ($scheme === 'wss' ? 443 : 80)),
            'has_path' => $path !== '',
            'ws_path' => $path === '' ? '' : '/'.$path,
            'key' => $key === '' ? self::KEY_PLACEHOLDER : $key,
            'url' => sprintf(
                '%s://%s%s%s/app/%s',
                $scheme,
                $host,
                $port ? ':'.$port : '',
                $path === '' ? '' : '/'.$path,
                $key === '' ? self::KEY_PLACEHOLDER : $key,
            ),
            'published' => $key !== '',
        ];
    }

    /**
     * Die absolute URL des Resync-Endpunkts.
     */
    #[Computed]
    public function changesUrl(): string
    {
        return route('api.changes.index');
    }

    /**
     * Die Grenze, ab der `data` nicht mehr mitfaehrt.
     */
    #[Computed]
    public function maxBroadcastBytes(): int
    {
        return ChangeRecorder::MAX_BROADCAST_BYTES;
    }

    /**
     * Die Aufbewahrungsfrist des Aenderungs-Logs — und damit die Verfallsfrist jedes
     * Cursors. Aus der Konfiguration, nicht abgeschrieben: wer sie dort hochsetzt,
     * moechte nicht, dass die Doku weiter die alte Zahl nennt.
     */
    #[Computed]
    public function pruneDays(): int
    {
        return (int) config('einundzwanzig.change_log.prune_days', 30);
    }

    /**
     * Die Ressourcen-Namen, die im Feed ueberhaupt vorkommen — aus dem Recorder
     * gelesen, nicht abgeschrieben. Kommt eine siebte dazu, steht sie hier.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function resourceNames(): array
    {
        return ChangeRecorder::resourceNames();
    }

    /**
     * Die zwei Kanaele, die es gibt. Es sind zwei, und die Liste ist vollstaendig.
     *
     * @return list<array{name:string, events:string, purpose:string}>
     */
    #[Computed]
    public function channels(): array
    {
        return [
            [
                'name' => 'portal',
                'events' => 'every event of every resource',
                'purpose' => 'The firehose: one subscription, one handler, all six resources. This is the "clear caches instantly" case.',
            ],
            [
                'name' => 'meetup-events',
                'events' => 'meetup-event.created · meetup-event.updated · meetup-event.deleted',
                'purpose' => 'Meetup dates only. Nothing else is ever published here — no cities, no courses, not even the meetup that owns the date.',
            ],
        ];
    }

    /**
     * Ein echtes Beispiel je Aktion.
     *
     * Die drei Objekte sind aus einem Testlauf gegen diese Anwendung entnommen und
     * dann nur in den Werten (Ids, Namen, Zeiten) auf etwas Lesbares gesetzt — die
     * Feldmenge ist die, die der Recorder wirklich erzeugt.
     *
     * @return list<array{title:string, note:HtmlString, json:string}>
     */
    #[Computed]
    public function payloadExamples(): array
    {
        $examples = [
            [
                'title' => 'meetup-event.created',
                'note' => 'A new meetup date. `data` is the complete object, in the same shape `GET /api/meetup-events` returns it — no `data` wrapper inside, because the REST API does not use one either.',
                'json' => <<<'JSON'
                {
                  "action": "created",
                  "resource": "meetup-event",
                  "id": 1234,
                  "sequence": 90412,
                  "occurred_at": "2026-08-23T16:23:11+00:00",
                  "api_version": "2.0.0",
                  "data": {
                    "id": 1234,
                    "meetup_id": 77,
                    "title": "Bitcoin-Stammtisch #42",
                    "start": "2026-09-18T18:00:00.000000Z",
                    "end": null,
                    "location": "Bürgerhaus, Seiteneingang",
                    "osm_type": null,
                    "osm_id": null,
                    "osm_name": null,
                    "osm_address": null,
                    "osm_lat": null,
                    "osm_lon": null,
                    "description": "Offener Abend, jeder ist willkommen.",
                    "link": "https://example.org/stammtisch",
                    "tags": [],
                    "recurrence_type": null,
                    "recurrence_day_of_week": null,
                    "recurrence_day_position": null,
                    "recurrence_interval": 1,
                    "recurrence_end_date": null,
                    "created_by": 3,
                    "created_at": "2026-08-23T16:23:11.000000Z",
                    "updated_at": "2026-08-23T16:23:11.000000Z"
                  },
                  "links": {
                    "self": null
                  }
                }
                JSON,
            ],
            [
                'title' => 'meetup.updated — the second event of the same write',
                'note' => 'Immediately after the date above, sequence 90413 carries the meetup itself: creating a date flipped `is_active` and `last_event_at`. Two records changed, so two entries. Look at `sequence` — it is one higher, not a repeat.',
                'json' => <<<'JSON'
                {
                  "action": "updated",
                  "resource": "meetup",
                  "id": 77,
                  "sequence": 90413,
                  "occurred_at": "2026-08-23T16:23:11+00:00",
                  "api_version": "2.0.0",
                  "data": {
                    "id": 77,
                    "name": "Bitcoin Meetup Dortmund",
                    "slug": "bitcoin-meetup-dortmund",
                    "city_id": 12,
                    "intro": "Jeden dritten Donnerstag.",
                    "telegram_link": "https://t.me/dortmund_btc",
                    "webpage": "https://dortmund.einundzwanzig.space",
                    "twitter_username": null,
                    "matrix_group": null,
                    "nostr": null,
                    "simplex": null,
                    "signal": null,
                    "community": "einundzwanzig",
                    "visible_on_map": 1,
                    "is_active": true,
                    "rsvp_enabled": true,
                    "attendees_public": true,
                    "logo": "https://portal.einundzwanzig.space/img/domains/de-DE.jpg",
                    "last_event_at": "2026-09-18T18:00:00.000000Z",
                    "created_by": 3,
                    "created_at": "2024-02-01T09:12:00.000000Z",
                    "updated_at": "2026-08-23T16:23:11.000000Z"
                  },
                  "links": {
                    "self": null
                  }
                }
                JSON,
            ],
            [
                'title' => 'meetup-event.deleted',
                'note' => '`data` is null — the record is gone, and no model in this application uses soft deletes. What you get instead is `previous`: the last known identifiers, enough to invalidate a cache entry by hand. This is the one event you cannot reconstruct from anywhere else.',
                'json' => <<<'JSON'
                {
                  "action": "deleted",
                  "resource": "meetup-event",
                  "id": 1234,
                  "sequence": 90598,
                  "occurred_at": "2026-08-24T08:02:44+00:00",
                  "api_version": "2.0.0",
                  "data": null,
                  "links": {
                    "self": null
                  },
                  "previous": {
                    "meetup_id": 77
                  }
                }
                JSON,
            ],
        ];

        return array_map(
            fn (array $example): array => [...$example, 'note' => $this->formatted($example['note'])],
            $examples,
        );
    }

    /**
     * Der Antwortumschlag von /api/changes.
     */
    #[Computed]
    public function envelopeExample(): string
    {
        return <<<'JSON'
        {
          "changes": [
            {
              "action": "created",
              "resource": "meetup-event",
              "id": 1234,
              "sequence": 90412,
              "occurred_at": "2026-08-23T16:23:11+00:00",
              "api_version": "2.0.0",
              "data": { "id": 1234, "meetup_id": 77, "title": "Bitcoin-Stammtisch #42" },
              "links": { "self": null }
            },
            {
              "action": "updated",
              "resource": "meetup",
              "id": 77,
              "sequence": 90413,
              "occurred_at": "2026-08-23T16:23:11+00:00",
              "api_version": "2.0.0",
              "data": { "id": 77, "name": "Bitcoin Meetup Dortmund", "is_active": true },
              "links": { "self": null }
            }
          ],
          "next_since": 90413,
          "has_more": false,
          "cursor_expired": false
        }
        JSON;
    }

    /**
     * Das TypeScript-Beispiel — copy-paste-faehig, mit den echten Werten dieser
     * Installation (oder den Platzhaltern, solange es keine gibt).
     */
    #[Computed]
    public function typescriptExample(): string
    {
        $connection = $this->connection();
        $changesUrl = $this->changesUrl();
        $forceTls = $connection['scheme'] === 'wss' ? 'true' : 'false';

        /*
         * `wsPath` steht nur da, wenn es einen Pfad gibt. Der Default in pusher-js ist
         * der leere String (`wsPath: opts.wsPath || Defaults.wsPath`, `wsPath: ''` in
         * src/core/defaults.ts) — weglassen und `''` sind also dasselbe. Eine Zeile
         * `wsPath: ''` in ein Copy-paste-Beispiel zu schreiben, laedt trotzdem dazu
         * ein, sie fuer noetig zu halten und irgendwann falsch zu fuellen.
         */
        $wsPathConst = $connection['has_path']
            ? "\nconst WS_PATH = '{$connection['ws_path']}';   // the proxy only forwards this prefix"
            : '';
        $wsPathOption = $connection['has_path']
            ? "\n  wsPath: WS_PATH,"
            : '';
        $maxBytes = ChangeRecorder::MAX_BROADCAST_BYTES;
        $pruneDays = $this->pruneDays();

        return <<<TS
        // npm i pusher-js
        //
        // One file, both halves: the socket delivers changes while you are connected,
        // and /api/changes closes the gap of everything you missed while you were not.
        // Neither half is optional. The socket has no delivery guarantee.

        import Pusher from 'pusher-js';

        const WS_HOST = '{$connection['host']}';
        const APP_KEY = '{$connection['key']}';{$wsPathConst}
        const CHANGES = '{$changesUrl}';

        type Change = {
          action: 'created' | 'updated' | 'deleted';
          resource: 'meetup' | 'meetup-event' | 'city' | 'course' | 'course-event' | 'lecturer';
          id: number;
          sequence: number;
          occurred_at: string;
          api_version: string;
          data: Record<string, unknown> | null;
          links: { self: string | null };
          previous?: Record<string, unknown>;
          truncated?: boolean;
        };

        type ChangesResponse = {
          changes: Change[];
          next_since: number | null;
          has_more: boolean;
          cursor_expired: boolean;
        };

        // Persist this. A cursor that only lives in memory is no cursor at all:
        // after a restart you cannot tell "nothing happened" from "I missed a deletion".
        let cursor: number | null = loadCursor();

        function loadCursor(): number | null {
          // Replace with your own storage (file, Redis, a column — anything that survives).
          return null;
        }

        function saveCursor(value: number | null): void {
          cursor = value;
        }

        function apply(change: Change): void {
          // Idempotent, please: the same sequence may arrive twice — once over the
          // socket, once again through the catch-up below. Deduplicate on `sequence`.
          console.log(change.sequence, change.action, change.resource, change.id);
        }

        /** Drain /api/changes from the stored cursor until there is nothing left. */
        async function catchUp(): Promise<void> {
          for (;;) {
            const url = new URL(CHANGES);
            if (cursor !== null) {
              url.searchParams.set('since', String(cursor));
            }
            url.searchParams.set('limit', '1000');

            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
              throw new Error(`/api/changes returned \${response.status}`);
            }

            const page = (await response.json()) as ChangesResponse;

            if (page.cursor_expired) {
              // Your cursor is older than the oldest row still on file ({$pruneDays}-day prune).
              // Changes happened that this endpoint can no longer hand out — deletions
              // above all. Re-read the regular endpoints in full, then continue from the
              // next_since OF THIS VERY ANSWER, not from the old cursor.
              await fullResync();
              saveCursor(page.next_since);
              return;
            }

            page.changes.forEach(apply);
            saveCursor(page.next_since);

            if (!page.has_more) {
              return;
            }
          }
        }

        async function fullResync(): Promise<void> {
          // GET /api/meetups, /api/meetup-events, /api/cities, /api/courses,
          // /api/course-events, /api/lecturers — and drop whatever is no longer in them.
        }

        const pusher = new Pusher(APP_KEY, {
          wsHost: WS_HOST,{$wsPathOption}
          wsPort: {$connection['port']},
          wssPort: {$connection['port']},
          forceTLS: {$forceTls},
          enabledTransports: ['ws', 'wss'],
          disableStats: true,
          // No `cluster`: pusher-js only needs one when it has to resolve the host itself.
        });

        // A rejected origin still completes the handshake with HTTP 101. The refusal
        // arrives afterwards, as a frame with code 4009. Bind this, or you will sit on a
        // socket you believe is connected and stay silent forever.
        pusher.connection.bind('error', (error: any) => {
          const code = error?.error?.data?.code ?? error?.data?.code;
          console.error('reverb connection error', code, error);
        });

        pusher.connection.bind('state_change', (states: { previous: string; current: string }) => {
          console.log('reverb', states.previous, '->', states.current);
        });

        // Every reconnect — not just the first connect — has to be followed by a
        // catch-up. Whatever happened while the socket was down was never queued.
        pusher.connection.bind('connected', () => {
          catchUp().catch((error) => console.error('catch-up failed', error));
        });

        // The firehose. Subscribe to `meetup-events` instead if you only care about dates.
        const portal = pusher.subscribe('portal');

        // With raw pusher-js you bind the name that is actually on the wire — no leading
        // dot. (With laravel-echo you would write .listen('.meetup-event.created'): that
        // dot is Echo's "no namespace" marker, Echo strips it and subscribes to the very
        // same name.) A name that never matches is silent, exactly like a channel that
        // does not exist.
        for (const resource of ['meetup', 'meetup-event', 'city', 'course', 'course-event', 'lecturer']) {
          for (const action of ['created', 'updated', 'deleted']) {
            portal.bind(`\${resource}.\${action}`, (change: Change) => {
              if (change.truncated) {
                // Over {$maxBytes} bytes: `data` was dropped for this hop.
                // The complete object is in /api/changes under the same sequence.
                catchUp().catch((error) => console.error('catch-up failed', error));
                return;
              }
              apply(change);
            });
          }
        }
        TS;
    }

    /**
     * Die vier Dinge, die ein Konsument sonst falsch macht.
     *
     * @return list<array{icon:string, title:HtmlString, body:HtmlString}>
     */
    #[Computed]
    public function pitfalls(): array
    {
        $pitfalls = [
            [
                'icon' => 'square-2-stack',
                'title' => 'One write can produce two events. That is not a double send.',
                'body' => 'Saving a meetup date recalculates the activity state of the meetup that owns it. If `is_active` or `last_event_at` change with it, a second entry goes out for the `meetup` resource — right after the `meetup-event` one, with the next sequence number. Both are real changes to two different records, and both are what keeps a cached meetup list from quietly going stale on exactly those two fields. This happens on the channel and in `/api/changes` alike, because both read the same row. The rule is "one entry per changed record", never "one entry per request".',
            ],
            [
                'icon' => 'clock',
                'title' => '`cursor_expired: true` means your cache is wrong, not that nothing happened.',
                'body' => 'The change log is pruned after '.$this->pruneDays().' days, which makes '.$this->pruneDays().' days a cursor expiry date. If you send a cursor older than the oldest row still on file, the endpoint cannot hand out what happened in between — deletions above all, because no model uses soft deletes and there is nowhere else to learn about them. The field is `true` exactly then, and present as `false` in every other answer, so you can read it without checking whether it exists. On `true`: read the regular endpoints in full, drop whatever is no longer in them, and then continue with the `next_since` of that same answer — not with your old cursor, and not with the `next_since` of a later call.',
            ],
            [
                'icon' => 'link-slash',
                'title' => '`links.self` is null for four of the six resources. Nothing is broken.',
                'body' => 'Only `course` and `lecturer` have a public show endpoint today, so only they get a link. For `meetup`, `meetup-event`, `city` and `course-event` the field is `null` on purpose — a URL that answers 404 would be worse than an honest null. You do not need it: `data` already carries the complete object, which is the whole point of the payload. If you do need to re-fetch, `/api/changes` is the way.',
            ],
            [
                'icon' => 'exclamation-triangle',
                'title' => 'A rejected origin looks exactly like success.',
                'body' => 'The WebSocket handshake completes with HTTP 101 and only then does the server send a frame with `code 4009, "Origin not allowed"`. A client that checks the status code and nothing else believes it is connected and stays silent forever — the same failure class as subscribing to a channel that does not exist. Bind the connection `error` event and log the code. (Today `allowed_origins` is `*`, measured and decided: a concrete list rejects every client that sends no `Origin` header at all, which is precisely what a server-side consumer does.)',
            ],
        ];

        return array_map(
            fn (array $pitfall): array => [
                ...$pitfall,
                'title' => $this->formatted($pitfall['title']),
                'body' => $this->formatted($pitfall['body']),
            ],
            $pitfalls,
        );
    }

    /**
     * Die sechs bewusst akzeptierten Luecken, ohne Beschoenigung.
     *
     * @return list<array{title:HtmlString, body:HtmlString}>
     */
    #[Computed]
    public function gaps(): array
    {
        $gaps = [
            [
                'title' => 'No delivery guarantee',
                'body' => 'Anything that happens while you are not connected is not kept for you. There is no offline queue and no per-consumer state — the server does not know you exist. `/api/changes` is the replacement, and it is the reason the socket is allowed to be this simple.',
            ],
            [
                'title' => 'No retry — a single failed broadcast loses the event on the channel for good',
                'body' => 'The queue that carries a broadcast from the application to Reverb runs with `tries = 1`. One failed attempt and the event is gone from the channel — no second attempt, no dead-letter entry you could replay. The row in the change log is written before the broadcast is even attempted and survives it, so `/api/changes` still has the change. That is not a nice-to-have fallback: for this class of failure it is the only copy.',
            ],
            [
                'title' => 'No replay in the protocol',
                'body' => 'Pusher-protocol channels have no "give me what I missed". You cannot ask the socket for sequence 90412. `/api/changes?since=90411` can.',
            ],
            [
                'title' => 'No per-message signature',
                'body' => 'Messages are not signed. Authenticity rests on TLS and the hostname you connected to, nothing else. `REVERB_APP_SECRET` protects the path from the application to Reverb — it says nothing about the message you receive.',
            ],
            [
                'title' => 'No guaranteed ordering across reconnects',
                'body' => 'Within one connection events arrive in the order they were sent. Across a reconnect nothing is promised. `sequence` is strictly increasing and gap-free at the source, so a gap you can see is a gap you can fix — sort by it, deduplicate by it, and use it as the cursor.',
            ],
            [
                'title' => 'No Pusher webhooks, and no HTTP webhooks either',
                'body' => 'Reverb does not implement the Pusher webhook API (laravel/reverb#64 was closed without an implementation), and this portal does not POST to registered URLs with an HMAC and a retry schedule. If that is what you need, say so on the issue — the change log is the substrate a webhook fan-out would read, so it is buildable, it is just not built.',
            ],
        ];

        return array_map(
            fn (array $gap): array => [
                'title' => $this->formatted($gap['title']),
                'body' => $this->formatted($gap['body']),
            ],
            $gaps,
        );
    }
}; ?>

<div class="relative min-h-screen overflow-hidden text-zinc-900 dark:text-white">
    {{-- Dekorativer Hintergrund-Glow, wie auf /ki-assistent --}}
    <div aria-hidden="true"
         class="pointer-events-none absolute inset-x-0 top-0 -z-10 h-[40rem] bg-[radial-gradient(60rem_30rem_at_50%_-10%,rgba(249,115,22,0.18),transparent)]"></div>

    {{-- Kopfleiste --}}
    <header class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-6 py-6">
        <a href="{{ route('welcome') }}" wire:navigate class="flex items-center gap-3" aria-label="{{ __('EINUNDZWANZIG') }}">
            <div class="size-10">
                <x-app-logo-icon/>
            </div>
            <span class="hidden text-sm font-semibold tracking-tight sm:block">{{ __('EINUNDZWANZIG') }}</span>
        </a>

        <flux:button :href="route('scramble.docs.ui')" variant="ghost" icon="book-open-text" size="sm">
            API reference
        </flux:button>
    </header>

    {{-- Hero --}}
    <section class="mx-auto max-w-4xl px-6 pt-6 pb-12 text-center sm:pt-12">
        <flux:badge color="orange" size="sm" icon="bolt" class="mb-6">Realtime</flux:badge>

        <h1 class="mx-auto max-w-3xl text-balance text-4xl font-bold tracking-tight sm:text-5xl">
            Realtime change feed
        </h1>

        <p class="mx-auto mt-6 max-w-2xl text-pretty text-lg text-zinc-600 dark:text-zinc-300">
            Learn that a meetup, a date, a city, a course, a course event or a lecturer was created,
            changed or deleted — instead of diffing a fresh export against an old cache.
        </p>
    </section>

    {{-- Zwei Wege --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/[0.06] p-6 sm:p-8">
                <div class="flex items-center gap-3">
                    <flux:icon name="shield-check" class="size-5 text-emerald-500"/>
                    <flux:heading size="lg">
                        <a href="{{ $this->changesUrl }}" class="underline decoration-emerald-500/40 underline-offset-4">GET /api/changes</a>
                        — the reliable way
                    </flux:heading>
                </div>
                <p class="mt-3 text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    A cursor-paginated change log. It replays, it survives your downtime, and it is the
                    only place a deletion can ever be learned about after the fact. Public, no token,
                    same 60 requests/minute as the rest of the read API. If you build only one half,
                    build this one.
                </p>
            </div>

            <div class="rounded-2xl border border-orange-500/30 bg-orange-500/[0.06] p-6 sm:p-8">
                <div class="flex items-center gap-3">
                    <flux:icon name="bolt" class="size-5 text-orange-500"/>
                    <flux:heading size="lg">The WebSocket — the fast way</flux:heading>
                </div>
                <p class="mt-3 text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    Two public channels on a Laravel Reverb server (Pusher protocol), no authentication.
                    You hear about a change within milliseconds instead of within a poll interval.
                    It carries no guarantee of any kind: not delivery, not retry, not replay, not
                    ordering across a reconnect.
                </p>
            </div>
        </div>

        <div class="mt-4 flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
            <flux:icon name="arrow-path" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
            <p class="text-pretty leading-relaxed text-zinc-700 dark:text-zinc-200">
                <strong>After every reconnect, catch up over <code class="rounded bg-zinc-900/5 px-1 py-0.5 font-mono text-sm dark:bg-white/10">/api/changes</code>.</strong>
                Not only after the first connect — after every single one. Nothing that happened while
                your socket was down was queued for you, and no error will tell you that you missed it.
                The two halves are one design: the socket says <em>now</em>, the endpoint says <em>completely</em>.
            </p>
        </div>
    </section>

    {{-- Verbindung --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-6">Connecting</flux:heading>

        @unless ($this->connection['published'])
            <div class="mb-6 flex items-start gap-4 rounded-2xl border border-amber-500/40 bg-amber-500/[0.08] p-6">
                <flux:icon name="wrench-screwdriver" class="mt-0.5 size-5 shrink-0 text-amber-500"/>
                <p class="text-pretty leading-relaxed text-zinc-700 dark:text-zinc-200">
                    <strong>The socket is not published in this environment yet.</strong>
                    The app key in curly braces below is a placeholder named after the environment
                    variable it will come from. Every value on this page is read from the running
                    configuration, so the moment the server is deployed they appear here by
                    themselves — there is nothing on this page to keep up to date by hand.
                </p>
            </div>
        @endunless

        <div class="overflow-hidden rounded-2xl border border-zinc-200 dark:border-white/10">
            <table class="w-full text-left text-sm">
                <tbody class="divide-y divide-zinc-200 dark:divide-white/10">
                    <tr class="bg-white/60 dark:bg-white/[0.03]">
                        <th scope="row" class="w-56 px-5 py-4 align-top font-semibold">WebSocket URL</th>
                        <td class="px-5 py-4 font-mono break-all">{{ $this->connection['url'] }}</td>
                    </tr>
                    <tr class="bg-white/60 dark:bg-white/[0.03]">
                        <th scope="row" class="px-5 py-4 align-top font-semibold">Host</th>
                        <td class="px-5 py-4">
                            <span class="font-mono break-all">{{ $this->connection['host'] }}</span>
                            <span class="mt-1 block text-zinc-500 dark:text-zinc-400">
                                The socket has its own hostname with its own certificate — it is not the
                                portal domain. The REST endpoints, <code>/api/changes</code> included, stay
                                where they are.
                            </span>
                        </td>
                    </tr>
                    <tr class="bg-white/60 dark:bg-white/[0.03]">
                        <th scope="row" class="px-5 py-4 align-top font-semibold">Scheme / port</th>
                        <td class="px-5 py-4 font-mono">{{ $this->connection['scheme'] }} / {{ $this->connection['port'] }}</td>
                    </tr>
                    <tr class="bg-white/60 dark:bg-white/[0.03]">
                        <th scope="row" class="px-5 py-4 align-top font-semibold"><code>wsPath</code></th>
                        <td class="px-5 py-4">
                            @if ($this->connection['has_path'])
                                <span class="font-mono">{{ $this->connection['ws_path'] }}</span>
                                <span class="mt-1 block text-zinc-500 dark:text-zinc-400">
                                    A path prefix is in play here: only this prefix is proxied through to
                                    Reverb, so <code>wsPath</code> has to be set on the client.
                                </span>
                            @else
                                <span class="font-mono">— none —</span>
                                <span class="mt-1 block text-zinc-500 dark:text-zinc-400">
                                    No prefix. The handshake sits at the default <code>/app</code> of its own
                                    hostname, so leave <code>wsPath</code> out entirely — in
                                    <code>pusher-js</code> it defaults to the empty string, and omitting it
                                    and passing <code>''</code> are the same thing.
                                </span>
                            @endif
                        </td>
                    </tr>
                    <tr class="bg-white/60 dark:bg-white/[0.03]">
                        <th scope="row" class="px-5 py-4 align-top font-semibold">App key</th>
                        <td class="px-5 py-4">
                            <span class="font-mono break-all">{{ $this->connection['key'] }}</span>
                            <span class="mt-1 block text-zinc-500 dark:text-zinc-400">
                                Public by design. There is no secret to hand out, because there is nothing to
                                authenticate: both channels are public and carry only data the REST API serves
                                without a token.
                            </span>
                        </td>
                    </tr>
                    <tr class="bg-white/60 dark:bg-white/[0.03]">
                        <th scope="row" class="px-5 py-4 align-top font-semibold">Authentication</th>
                        <td class="px-5 py-4">None. No <code>/broadcasting/auth</code> round trip — there are no
                            private or presence channels.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- Kanaele --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">Channels</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            There are two. This table is the complete list — if a channel name is not in it, it does not
            exist, and subscribing to it will succeed and stay silent forever. Country, city, entity and
            RSVP channels have been discussed and are deliberately not built.
        </p>

        <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-white/10">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-white/[0.06] dark:text-zinc-400">
                    <tr>
                        <th scope="col" class="px-5 py-3">Channel</th>
                        <th scope="col" class="px-5 py-3">Carries</th>
                        <th scope="col" class="px-5 py-3">What for</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white/60 dark:divide-white/10 dark:bg-white/[0.03]">
                    @foreach ($this->channels as $channel)
                        <tr wire:key="channel-{{ $channel['name'] }}">
                            <td class="px-5 py-4 align-top font-mono font-semibold text-orange-500">{{ $channel['name'] }}</td>
                            <td class="px-5 py-4 align-top font-mono text-xs">{{ $channel['events'] }}</td>
                            <td class="px-5 py-4 align-top text-zinc-600 dark:text-zinc-300">{{ $channel['purpose'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- Event-Namen --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">Event names</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            An event name is <code>&lt;resource&gt;.&lt;action&gt;</code> — six resources, three actions,
            eighteen names in total. All eighteen appear on <code>portal</code>; the three
            <code>meetup-event</code> ones also on <code>meetup-events</code>.
        </p>

        <div class="mb-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->resourceNames as $resource)
                <div wire:key="resource-{{ $resource }}"
                     class="rounded-xl border border-zinc-200 bg-white/60 p-5 dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="font-mono text-sm font-semibold text-orange-500">{{ $resource }}</div>
                    <ul class="mt-2 space-y-1 font-mono text-xs text-zinc-600 dark:text-zinc-300">
                        <li>{{ $resource }}.created</li>
                        <li>{{ $resource }}.updated</li>
                        <li>{{ $resource }}.deleted</li>
                    </ul>
                </div>
            @endforeach
        </div>

        <div class="flex items-start gap-4 rounded-2xl border border-orange-500/30 bg-orange-500/[0.06] p-6">
            <flux:icon name="ellipsis-horizontal-circle" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
            <div>
                <flux:heading size="lg" class="mb-2">The leading dot: where it belongs, and where it does not</flux:heading>
                <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    Laravel examples show event names written with a leading dot, and that trips people
                    up. The dot is <strong>client syntax, not part of the name</strong>: it tells
                    <code>laravel-echo</code> not to prepend an application namespace, and Echo removes
                    it before subscribing. On the wire the event is called
                    <code>meetup-event.created</code>, full stop.
                </p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">Raw pusher-js — no dot</div>
                        <pre class="overflow-x-auto text-xs leading-relaxed"><code>pusher
  .subscribe('meetup-events')
  .bind('meetup-event.created', handler);</code></pre>
                    </div>
                    <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-white/10 dark:bg-zinc-900">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">laravel-echo — with the dot</div>
                        <pre class="overflow-x-auto text-xs leading-relaxed"><code>Echo
  .channel('meetup-events')
  .listen('.meetup-event.created', handler);</code></pre>
                    </div>
                </div>
                <p class="mt-4 text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    Both subscribe to the same event. Get it wrong in either direction — a dot passed to
                    <code>bind()</code>, or none passed to <code>listen()</code> — and you subscribe to a
                    name nobody sends. That succeeds, and then stays silent forever.
                </p>
            </div>
        </div>
    </section>

    {{-- Payload-Vertrag --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">The payload contract</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            Every event, on either channel, is one envelope. The exact same envelope comes back out of
            <code>/api/changes</code> — byte for byte, because it is built once and stored, not rebuilt
            per transport. <code>data</code> follows the shape of the matching REST resource, so you do
            not need a second set of types.
        </p>

        <div class="mb-8 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-white/10">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-white/[0.06] dark:text-zinc-400">
                    <tr>
                        <th scope="col" class="px-5 py-3">Field</th>
                        <th scope="col" class="px-5 py-3">Meaning</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white/60 dark:divide-white/10 dark:bg-white/[0.03]">
                    <tr>
                        <td class="px-5 py-3 font-mono">action</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300"><code>created</code>, <code>updated</code> or <code>deleted</code>.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">resource</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">One of {{ implode(', ', $this->resourceNames) }}.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">id</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">Primary key of the record that changed.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">sequence</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">Strictly increasing, and the cursor you send back as <code>since</code>. Deduplicate on it.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">occurred_at</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">ISO 8601, the moment of the change — not the moment of delivery.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">api_version</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">The REST API version whose resource shape <code>data</code> follows.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">data</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">The complete object, bare — no <code>data</code> wrapper inside. <code>null</code> on <code>deleted</code>, and <code>null</code> when the envelope was too large (see below).</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">links.self</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300"><code>null</code> for four of the six resources. Normal — see the pitfalls below.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">previous</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">Only on <code>deleted</code>: the last known identifiers of the record that is gone.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">truncated</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">Only present, and only <code>true</code>, when <code>data</code> was dropped for size.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        @foreach ($this->payloadExamples as $example)
            <div wire:key="payload-{{ $loop->index }}" class="mb-6">
                <flux:heading size="lg" class="mb-2 font-mono">{{ $example['title'] }}</flux:heading>
                <p class="mb-3 text-pretty text-sm text-zinc-600 dark:text-zinc-300">{!! $example['note'] !!}</p>
                <pre class="overflow-x-auto rounded-xl border border-zinc-200 bg-white p-5 text-xs leading-relaxed dark:border-white/10 dark:bg-zinc-900"><code>{{ $example['json'] }}</code></pre>
            </div>
        @endforeach

        <div class="mt-8 flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
            <flux:icon name="scissors" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
            <div>
                <flux:heading size="lg" class="mb-2">The {{ number_format($this->maxBroadcastBytes, 0, '.', ' ') }} byte limit</flux:heading>
                <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    Reverb rejects anything above <code>max_request_size</code> ({{ number_format($this->maxBroadcastBytes, 0, '.', ' ') }} bytes)
                    with HTTP 413, so an envelope that would exceed it goes out over the socket as
                    <code>"data": null, "truncated": true</code> instead of not going out at all. The stored
                    row keeps the complete object: fetch it from
                    <a href="{{ $this->changesUrl }}" class="underline underline-offset-4">/api/changes</a>
                    under the same <code>sequence</code>. Measured against the whole envelope, not just
                    <code>data</code> — a long event description is the usual cause.
                </p>
            </div>
        </div>
    </section>

    {{-- /api/changes --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">The <code>/api/changes</code> envelope</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            This is the one endpoint in the API that wraps its list in an object instead of returning a
            bare array — the cursor has to travel with the answer. The list is called
            <code>changes</code>, not <code>data</code>, because every entry already carries a
            <code>data</code> field of its own.
        </p>

        <pre class="mb-6 overflow-x-auto rounded-xl border border-zinc-200 bg-white p-5 text-xs leading-relaxed dark:border-white/10 dark:bg-zinc-900"><code>{{ $this->envelopeExample }}</code></pre>
        <p class="mb-8 text-sm text-zinc-500 dark:text-zinc-400">
            (<code>data</code> shortened here for readability — in the real answer it is the complete
            object, exactly as in the examples above.)
        </p>

        <div class="mb-8 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-white/10">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-white/[0.06] dark:text-zinc-400">
                    <tr>
                        <th scope="col" class="px-5 py-3">Field</th>
                        <th scope="col" class="px-5 py-3">Meaning</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white/60 dark:divide-white/10 dark:bg-white/[0.03]">
                    <tr>
                        <td class="px-5 py-3 font-mono">changes</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">The entries, ascending by <code>sequence</code>. Same envelope as on the channel.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">next_since</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">The cursor for your next call. On an empty page it is the cursor you sent, not <code>null</code> — so a quiet minute does not rewind you to the beginning.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">has_more</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300"><code>true</code> when <code>limit</code> cut the page off. Keep calling until it is <code>false</code>.</td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3 font-mono">cursor_expired</td>
                        <td class="px-5 py-3 text-zinc-600 dark:text-zinc-300">Present in every answer, including as <code>false</code>. On <code>true</code>, your cache is incomplete — see the pitfalls below.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <flux:heading size="lg" class="mb-3">The cursor loop</flux:heading>
        <p class="mb-4 text-pretty text-zinc-600 dark:text-zinc-300">
            <code>since</code> → read <code>next_since</code> → send it back as <code>since</code>. That is
            the whole protocol. The cursor is exclusive: you never receive the entry you named again.
        </p>
        <pre class="overflow-x-auto rounded-xl border border-zinc-200 bg-white p-5 text-xs leading-relaxed dark:border-white/10 dark:bg-zinc-900"><code># First call ever — no cursor. You get the newest 100 entries (limit, max 1000)
# just to pick up a starting point, and has_more is false.
curl '{{ $this->changesUrl }}?limit=1'

# → { "changes": [ … ], "next_since": 90413, "has_more": false, "cursor_expired": false }

# Every call after that: send back what you were given.
curl '{{ $this->changesUrl }}?since=90413'

# Only interested in dates? Filter — repeatable or comma separated.
curl '{{ $this->changesUrl }}?since=90413&resource=meetup-event,meetup'

# A timestamp works as a cursor too, for a consumer that only knows when it last synced.
curl '{{ $this->changesUrl }}?since=2026-08-23T16:00:00%2B00:00'</code></pre>
    </section>

    {{-- TypeScript --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">A working TypeScript client</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            Both halves in one file: subscribe to the channel, and catch up over
            <code>/api/changes</code> on every <code>connected</code>. The only dependency is
            <code>pusher-js</code> — Reverb speaks the Pusher protocol, so no Laravel-specific client is
            needed.
        </p>
        <pre class="overflow-x-auto rounded-xl border border-zinc-200 bg-white p-5 text-xs leading-relaxed dark:border-white/10 dark:bg-zinc-900"><code>{{ $this->typescriptExample }}</code></pre>
    </section>

    {{-- Stolperfallen --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">Four things that go wrong</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            None of these produce an error message. That is what they have in common, and why they are
            listed here rather than left to be discovered.
        </p>

        <div class="space-y-4">
            @foreach ($this->pitfalls as $pitfall)
                <div wire:key="pitfall-{{ $loop->index }}"
                     class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="shrink-0 rounded-lg bg-orange-500/10 p-2.5 text-orange-500">
                        <flux:icon name="{{ $pitfall['icon'] }}" class="size-6"/>
                    </div>
                    <div>
                        <flux:heading size="lg" class="mb-2">{!! $pitfall['title'] !!}</flux:heading>
                        <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">{!! $pitfall['body'] !!}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Luecken --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">Six gaps, accepted on purpose</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            None of these are oversights and none are on a roadmap. They are the price of a transport
            that is this simple, and they are listed so you can decide whether it is a price you can pay.
            Every one of them is survivable in the same way: <code>/api/changes</code>.
        </p>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($this->gaps as $gap)
                <div wire:key="gap-{{ $loop->index }}"
                     class="rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                    <div class="flex items-start gap-3">
                        <flux:icon name="minus-circle" class="mt-0.5 size-5 shrink-0 text-zinc-400"/>
                        <div>
                            <flux:heading size="lg" class="mb-2">{!! $gap['title'] !!}</flux:heading>
                            <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">{!! $gap['body'] !!}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Betrieb --}}
    <section class="mx-auto max-w-5xl px-6 pb-24">
        <flux:heading size="xl" class="mb-6">Operations</flux:heading>

        <div class="space-y-4">
            <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <flux:icon name="arrow-path-rounded-square" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
                <div>
                    <flux:heading size="lg" class="mb-2">Every deploy drops every connection</flux:heading>
                    <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                        The server is restarted as part of the deploy, and the restart has no graceful
                        drain: all sockets close at once, without a goodbye frame. That is normal and it
                        happens whenever the portal ships. Plan for reconnect with backoff — and for the
                        catch-up over <code>/api/changes</code> that follows it, because the changes made
                        during the restart window were not kept for you.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <flux:icon name="chat-bubble-left-right" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
                <div>
                    <flux:heading size="lg" class="mb-2">Missing a channel you need?</flux:heading>
                    <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                        Country-scoped, city-scoped and per-entity channels are designed but not built,
                        because a published channel name is a contract whose breach is silent at the
                        subscriber. They get built when a consumer asks for them by name — say so on
                        <a href="https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/issues/29"
                           class="underline underline-offset-4" target="_blank" rel="noopener">issue #29</a>.
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>
