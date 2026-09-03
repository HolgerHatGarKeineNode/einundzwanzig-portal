<?php

use App\Attributes\SeoDataAttribute;
use App\Jobs\DeliverWebhookJob;
use App\Support\Broadcasting\ChangeRecorder;
use App\Traits\SeoTrait;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Consumer documentation for the outbound webhooks (Issue #36, DoD box 6).
 *
 * DIESE SEITE IST AUF ENGLISCH UND WIRD NICHT UEBERSETZT — aus demselben Grund wie
 * ihre Schwesterseite /docs/websockets, deren Aufbau sie spiegelt: sie steht neben
 * /docs/api, beschreibt denselben Vertrag mit denselben Feldnamen, und Scalar ist in
 * `config/scramble.php` fest auf `locale => 'en'` genagelt. Ein Header-Name in neun
 * Sprachen gerahmt macht den Vergleich schwerer, nicht leichter.
 *
 * JEDE ZAHL AUF DIESER SEITE KOMMT AUS DER KONFIGURATION ODER AUS DEM CODE, nicht aus
 * dem Fliesstext: Timeout, Backoff-Plan, Auto-Abschaltung, die abonnierbaren
 * Ressourcen, die Aufbewahrungsfrist des Logs. Wer eine davon aendert, aendert die
 * Seite mit — der Fehler, den diese Seite am teuersten machen koennte, ist ein
 * Retry-Plan, der irgendwann nicht mehr der gefahrene ist, und niemand sieht es.
 */
new
#[Layout('components.layouts.auth')]
#[SeoDataAttribute(key: 'docs_webhooks')]
class extends Component
{
    use SeoTrait;

    /**
     * Fliesstext mit `Backticks` in Fliesstext mit <code>-Auszeichnung.
     *
     * Erst escapen, dann ersetzen — in dieser Reihenfolge, sonst waere die Methode ein
     * Weg, beliebiges HTML in die Seite zu schreiben. Uebernommen von
     * /docs/websockets, damit beide Seiten Inline-Code gleich auszeichnen.
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
     * Sekunden in eine lesbare Dauer, fuer die Retry-Tabelle.
     */
    private function duration(int $seconds): string
    {
        if ($seconds === 0) {
            return 'immediately';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return implode(' ', array_filter([
            $hours > 0 ? $hours.' h' : null,
            $minutes > 0 ? $minutes.' min' : null,
        ]));
    }

    /**
     * Die absolute URL der Abo-Verwaltung.
     */
    #[Computed]
    public function subscriptionsUrl(): string
    {
        return route('api.webhook-subscriptions.index');
    }

    /**
     * Die absolute URL des Resync-Endpunkts — der Weg aus jeder Luecke.
     */
    #[Computed]
    public function changesUrl(): string
    {
        return route('api.changes.index');
    }

    /**
     * Die Ressourcen, die ueberhaupt abonniert werden koennen. Aus der Config
     * gelesen: kommt `city` dazu, steht sie hier, ohne dass jemand diese Seite
     * anfasst.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function resources(): array
    {
        return (array) config('einundzwanzig.webhooks.allowed_resources', []);
    }

    /**
     * Sekunden, die ein Empfaenger fuer seine Antwort hat.
     */
    #[Computed]
    public function timeoutSeconds(): int
    {
        return (int) config('einundzwanzig.webhooks.timeout_seconds', 10);
    }

    /**
     * Fehlgeschlagene ZUSTELLUNGEN in Folge, nach denen das Abo abgeschaltet wird.
     */
    #[Computed]
    public function autoDisableAfter(): int
    {
        return (int) config('einundzwanzig.webhooks.auto_disable_after', 10);
    }

    /**
     * Startet ein neues Abo hinter der Freigabe eines Betreibers?
     */
    #[Computed]
    public function requiresApproval(): bool
    {
        return (bool) config('einundzwanzig.webhooks.require_approval', true);
    }

    /**
     * Die Aufbewahrungsfrist des Aenderungs-Logs — und damit die Frist, innerhalb
     * derer sich eine Luecke ueber /api/changes noch schliessen laesst.
     */
    #[Computed]
    public function pruneDays(): int
    {
        return (int) config('einundzwanzig.change_log.prune_days', 30);
    }

    /**
     * Der Zustellplan, aus `backoff_seconds` gerechnet statt abgeschrieben.
     *
     * Die Zahl der Versuche ist `count(backoff) + 1`, weil der erste Versuch keinen
     * Backoff hat — genau so rechnet {@see DeliverWebhookJob::__construct()}
     * `$tries`. Die Kumulierung ist der Wert, den ein Betreiber wirklich braucht:
     * "wie lange versucht ihr es, bevor ihr aufgeben".
     *
     * @return list<array{attempt:int, delay:string, cumulative:string}>
     */
    #[Computed]
    public function retrySchedule(): array
    {
        $backoff = array_values((array) config('einundzwanzig.webhooks.backoff_seconds', []));

        $schedule = [[
            'attempt' => 1,
            'delay' => 'immediately',
            'cumulative' => 'immediately',
        ]];

        $elapsed = 0;

        foreach ($backoff as $index => $seconds) {
            $elapsed += (int) $seconds;

            $schedule[] = [
                'attempt' => $index + 2,
                'delay' => '+'.$this->duration((int) $seconds),
                'cumulative' => '+'.$this->duration($elapsed),
            ];
        }

        return $schedule;
    }

    /**
     * Wie lange insgesamt versucht wird, bevor eine Zustellung als gescheitert gilt.
     */
    #[Computed]
    public function retryWindow(): string
    {
        return $this->duration(
            (int) array_sum((array) config('einundzwanzig.webhooks.backoff_seconds', []))
        );
    }

    /**
     * Die vier Header, die jede Zustellung traegt.
     *
     * @return list<array{name:string, value:string, note:HtmlString}>
     */
    #[Computed]
    public function headers(): array
    {
        $headers = [
            [
                'name' => 'X-Portal-Event',
                'value' => '<resource>.<action>',
                'note' => 'Which change this is, without parsing the body: `meetup-event.updated`, `meetup.deleted`, and so on. Same names the WebSocket channel uses.',
            ],
            [
                'name' => 'X-Portal-Delivery',
                'value' => '<delivery id>',
                'note' => 'The idempotency key. It stays the SAME across every retry of this delivery, which is what makes it usable as one — a retry is not a new event.',
            ],
            [
                'name' => 'X-Portal-Timestamp',
                'value' => '<unix seconds>',
                'note' => 'The moment of THIS attempt, not of the change. It is part of the signed string, so a retry six hours later carries a fresh timestamp and a different signature over an identical body. The moment of the change is `occurred_at` inside the body.',
            ],
            [
                'name' => 'X-Portal-Signature',
                'value' => "hash_hmac('sha256', timestamp + '.' + rawBody, secret)",
                'note' => 'Lowercase hex, **without** a `sha256=` prefix — that is where this differs from GitHub, whose idea it otherwise is. Verify against the raw body before parsing.',
            ],
        ];

        return array_map(
            fn (array $header): array => [...$header, 'note' => $this->formatted($header['note'])],
            $headers,
        );
    }

    /**
     * Das Registrierungs-Beispiel, mit den echten URLs dieser Installation.
     */
    #[Computed]
    public function registrationExample(): string
    {
        $url = $this->subscriptionsUrl();
        $resources = implode('", "', $this->resources());

        return <<<SHELL
        # 1 — register. The token is a personal access token from Settings -> API Tokens.
        curl -X POST '{$url}' \\
          -H 'Authorization: Bearer <your-token>' \\
          -H 'Content-Type: application/json' \\
          -H 'Accept: application/json' \\
          -d '{"url": "https://example.org/portal-webhook", "resources": ["{$resources}"]}'

        # 2 — list your subscriptions (no secret in here, see below).
        curl '{$url}' -H 'Authorization: Bearer <your-token>' -H 'Accept: application/json'

        # 3 — pause, resume, or move the endpoint. Resuming a subscription we
        #     auto-disabled also clears its failure count.
        curl -X PATCH '{$url}/1' \\
          -H 'Authorization: Bearer <your-token>' \\
          -H 'Content-Type: application/json' \\
          -d '{"active": false}'

        # 4 — delete. Also the way to change the secret: there is no rotation in place.
        curl -X DELETE '{$url}/1' -H 'Authorization: Bearer <your-token>'
        SHELL;
    }

    /**
     * Die Antwort auf die Registrierung — der einzige Moment, in dem das Secret
     * ueberhaupt ausgeliefert wird.
     */
    #[Computed]
    public function registrationResponse(): string
    {
        $resources = implode('", "', $this->resources());
        $status = $this->requiresApproval() ? 'pending' : 'active';

        return <<<JSON
        HTTP/1.1 201 Created

        {
          "data": {
            "id": 1,
            "url": "https://example.org/portal-webhook",
            "resources": ["{$resources}"],
            "reveal_secret": false,
            "status": "{$status}",
            "active": true,
            "consecutive_failures": 0,
            "disabled_at": null,
            "created_at": "2026-09-03T17:25:00+00:00",
            "secret": "9f2c…  <- the only time you will see this"
          }
        }
        JSON;
    }

    /**
     * Ein echtes Beispiel je Aktion.
     *
     * Die Feldmenge ist die, die `MeetupEventResource` wirklich erzeugt — inklusive
     * der sechs `osm_*`-Felder und der Tag-Gestalt mit `name_locale`. Nur die Werte
     * (Ids, Namen, Zeiten) sind auf etwas Lesbares gesetzt.
     *
     * @return list<array{title:string, note:HtmlString, json:string}>
     */
    #[Computed]
    public function payloadExamples(): array
    {
        $examples = [
            [
                'title' => 'meetup-event.updated',
                'note' => 'The envelope, byte for byte as `GET /api/changes` returns it — it is stored once and shipped, not rebuilt per transport. `data` is the object in the shape of the REST resource, with no `data` wrapper inside, because the REST API does not use one either.',
                'json' => <<<'JSON'
                {
                  "action": "updated",
                  "resource": "meetup-event",
                  "id": 1234,
                  "sequence": 90412,
                  "occurred_at": "2026-09-03T16:23:11+00:00",
                  "api_version": "2.0.0",
                  "data": {
                    "id": 1234,
                    "meetup_id": 77,
                    "title": "Bitcoin-Stammtisch #42",
                    "start": "2026-09-18T18:00:00.000000Z",
                    "end": null,
                    "location": "Bürgerhaus, Seiteneingang",
                    "osm_type": "way",
                    "osm_id": 123456789,
                    "osm_name": "Bürgerhaus",
                    "osm_address": "Hauptstraße 1, 90402 Nürnberg",
                    "osm_lat": "49.4521000",
                    "osm_lon": "11.0767000",
                    "description": "Offener Abend, jeder ist willkommen.",
                    "link": "https://example.org/stammtisch",
                    "tags": [
                      {
                        "id": 5,
                        "type": "meetup_event",
                        "name": "Vortrag",
                        "name_locale": "de",
                        "slug": "vortrag",
                        "featured": true,
                        "approved": true,
                        "translations": {"de": "Vortrag", "en": "Talk", "cs": "Přednáška"}
                      }
                    ],
                    "recurrence_type": null,
                    "recurrence_day_of_week": null,
                    "recurrence_day_position": null,
                    "recurrence_interval": 1,
                    "recurrence_end_date": null,
                    "created_by": 3,
                    "created_at": "2025-12-30T20:20:10.000000Z",
                    "updated_at": "2026-09-03T16:23:11.000000Z"
                  },
                  "links": {
                    "self": null
                  }
                }
                JSON,
            ],
            [
                'title' => 'meetup.updated — the second delivery of the same write',
                'note' => 'Immediately after the date above, sequence 90413 carries the meetup itself: saving a date recalculates the activity state of the meetup that owns it. Two records changed, so two deliveries. Look at `sequence` — it is one higher, not a repeat. Only a subscription that includes `meetup` in its `resources` sees this one.',
                'json' => <<<'JSON'
                {
                  "action": "updated",
                  "resource": "meetup",
                  "id": 77,
                  "sequence": 90413,
                  "occurred_at": "2026-09-03T16:23:11+00:00",
                  "api_version": "2.0.0",
                  "data": {
                    "id": 77,
                    "name": "Bitcoin Meetup Dortmund",
                    "slug": "bitcoin-meetup-dortmund",
                    "city_id": 12,
                    "is_active": true,
                    "last_event_at": "2026-09-18T18:00:00.000000Z",
                    "…": "the full MeetupResource shape"
                  },
                  "links": {
                    "self": null
                  }
                }
                JSON,
            ],
            [
                'title' => 'meetup-event.deleted',
                'note' => '`data` is null — the record is gone, and no model in this application uses soft deletes. What you get instead is `previous`: the last known identifiers, enough to invalidate a cache entry by hand. This is the one event you cannot reconstruct from any read endpoint afterwards.',
                'json' => <<<'JSON'
                {
                  "action": "deleted",
                  "resource": "meetup-event",
                  "id": 1234,
                  "sequence": 90598,
                  "occurred_at": "2026-09-04T08:02:44+00:00",
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
     * Was `previous` je Ressource traegt — direkt aus dem Vertrag des Recorders.
     *
     * @return list<array{resource:string, fields:string}>
     */
    #[Computed]
    public function previousFields(): array
    {
        return [
            ['resource' => 'meetup', 'fields' => 'slug, city_id'],
            ['resource' => 'meetup-event', 'fields' => 'meetup_id'],
        ];
    }

    /**
     * Das TypeScript-Beispiel — der Grund, aus dem diese Seite existiert.
     *
     * NOWDOC mit strtr() statt Heredoc: ein TS-Template-Literal `${x}` waere in
     * einem Heredoc PHP-Interpolation (in 8.2 deprecated, in 9 weg), und der Fehler
     * faellt erst auf, wenn jemand den Schnipsel kopiert und er nicht laeuft. So
     * interpoliert PHP gar nichts und die vier eingesetzten Werte stehen sichtbar
     * unten.
     */
    #[Computed]
    public function typescriptExample(): string
    {
        return strtr(<<<'TS'
        // No dependencies: node:crypto is built in (Node 18+, Bun, Deno).
        //
        // Two rules decide whether this works at all, and getting either wrong fails
        // in the same silent way — every delivery looks forged:
        //
        //   1. Verify the RAW body, exactly as it arrived. Parse-and-re-serialise
        //      changes bytes (key order, spacing, escaped slashes, unicode) and the
        //      HMAC will not match. We send with unescaped slashes and unescaped
        //      unicode, and you must not rely on that either — do not re-encode.
        //   2. Compare in constant time. `===` on two hex strings gives away how many
        //      characters matched.

        import { createHmac, timingSafeEqual } from 'node:crypto';

        // Your own secret manager or env var. Never in the repository, never in a log
        // line. If it leaks, delete the subscription and create a new one — there is
        // no in-place rotation.
        const SECRET = process.env.PORTAL_WEBHOOK_SECRET!;

        // How old a delivery attempt may be. Replay protection, not clock tolerance:
        // the timestamp is the moment of the attempt, so a retry brings a fresh one.
        const MAX_AGE_SECONDS = 300;

        export type PortalEnvelope = {
          action: 'created' | 'updated' | 'deleted';
          resource: __RESOURCE_UNION__;
          id: number;
          sequence: number;
          occurred_at: string;
          api_version: string;
          data: Record<string, unknown> | null;   // null on `deleted`
          links: { self: string | null };
          previous?: Record<string, unknown>;     // only on `deleted`
        };

        /** Throws unless the signature, the timestamp and the body all check out. */
        export function verifyPortalWebhook(
          rawBody: Buffer,
          headers: { 'x-portal-timestamp'?: string; 'x-portal-signature'?: string },
        ): PortalEnvelope {
          const timestamp = headers['x-portal-timestamp'];
          const signature = headers['x-portal-signature'];

          if (!timestamp || !signature) {
            throw new Error('portal webhook: signature headers missing');
          }

          const age = Math.abs(Math.floor(Date.now() / 1000) - Number(timestamp));
          if (!Number.isFinite(age) || age > MAX_AGE_SECONDS) {
            throw new Error('portal webhook: stale timestamp');
          }

          // Lowercase hex over `<timestamp>.<rawBody>`, no `sha256=` prefix.
          const expected = createHmac('sha256', SECRET)
            .update(timestamp + '.')
            .update(rawBody)
            .digest('hex');

          const a = Buffer.from(expected, 'utf8');
          const b = Buffer.from(signature, 'utf8');

          // timingSafeEqual throws on different lengths, so check that first — and
          // check it on the buffers, not with a `===` shortcut on the strings.
          if (a.length !== b.length || !timingSafeEqual(a, b)) {
            throw new Error('portal webhook: bad signature');
          }

          return JSON.parse(rawBody.toString('utf8')) as PortalEnvelope;
        }

        // ---------------------------------------------------------------------------
        // Your side of the deal: one number to persist and five functions to write
        // ---------------------------------------------------------------------------

        // The highest `sequence` you have applied. Load it from your own storage at
        // boot: in memory it is worthless, because after a restart you cannot tell
        // "nothing happened" from "I missed a deletion".
        let lastSequence = 0;

        declare function alreadyHandled(deliveryId: string): Promise<boolean>;
        declare function remember(deliveryId: string): Promise<void>;
        declare function upsert(resource: string, data: Record<string, unknown>): Promise<void>;
        declare function dropFromCache(resource: string, id: number): Promise<void>;
        declare function fullResync(): Promise<void>;

        // ---------------------------------------------------------------------------
        // A receiver, with the three things that are easy to get wrong
        // ---------------------------------------------------------------------------

        import express from 'express';

        const app = express();

        // express.raw, NOT express.json: express.json() consumes the stream and leaves
        // you nothing to verify. Same trap everywhere — Next.js route handler:
        // `const rawBody = Buffer.from(await request.text())`.
        app.post('/portal-webhook', express.raw({ type: '*/*' }), async (req, res) => {
          let change: PortalEnvelope;

          try {
            change = verifyPortalWebhook(req.body as Buffer, req.headers as any);
          } catch (error) {
            // 4xx: we will retry, but a wrong secret will not fix itself.
            return res.sendStatus(400);
          }

          const deliveryId = req.header('x-portal-delivery')!;

          // At-least-once. The same delivery id comes back on every retry, and the
          // same `sequence` can also reach you through /api/changes. Either is an
          // idempotency key; persist it, a Set in memory forgets on restart.
          if (await alreadyHandled(deliveryId)) {
            return res.sendStatus(200);
          }

          // Answer FIRST, work afterwards. Anything but 2xx within __TIMEOUT__ s counts as a
          // failure, and __AUTO_DISABLE__ failed deliveries in a row disable the subscription.
          res.sendStatus(200);

          await remember(deliveryId);
          await apply(change);
        });

        async function apply(change: PortalEnvelope): Promise<void> {
          if (change.action === 'deleted') {
            // `data` is null here. The identifiers are in `previous`.
            await dropFromCache(change.resource, change.id);
          } else {
            await upsert(change.resource, change.data!);
          }

          // Ordering is not guaranteed across retries: a delivery that failed twice
          // can land after a newer one. Sort by `sequence`, and treat a gap as
          // "something did not arrive" rather than "nothing happened".
          if (lastSequence > 0 && change.sequence > lastSequence + 1) {
            await catchUp(lastSequence);
          }

          lastSequence = Math.max(lastSequence, change.sequence);
        }

        /** The gap closer. Also the only way back in after a pause or an outage. */
        async function catchUp(since: number): Promise<void> {
          const url = new URL('__CHANGES_URL__');
          url.searchParams.set('since', String(since));
          url.searchParams.set('resource', '__RESOURCE_LIST__');

          const page = await fetch(url, { headers: { Accept: 'application/json' } });
          const body = await page.json();

          if (body.cursor_expired) {
            // Your cursor is older than the oldest row still on file. Re-read the
            // regular endpoints in full, then continue from the next_since OF THIS
            // VERY ANSWER.
            await fullResync();
          }

          for (const change of body.changes) {
            await apply(change);
          }

          if (body.has_more) {
            await catchUp(body.next_since);
          }
        }
        TS, [
            '__RESOURCE_UNION__' => "'".implode("' | '", $this->resources())."'",
            '__RESOURCE_LIST__' => implode(',', $this->resources()),
            '__CHANGES_URL__' => $this->changesUrl(),
            '__TIMEOUT__' => (string) $this->timeoutSeconds(),
            '__AUTO_DISABLE__' => (string) $this->autoDisableAfter(),
        ]);
    }

    /**
     * Die Dinge, die ohne Fehlermeldung schiefgehen.
     *
     * @return list<array{icon:string, title:HtmlString, body:HtmlString}>
     */
    #[Computed]
    public function pitfalls(): array
    {
        $pitfalls = [
            [
                'icon' => 'document-text',
                'title' => 'Signing a re-serialised body never matches.',
                'body' => 'The signature is computed over the bytes we sent. Parse the JSON, serialise it again and you have a different byte string — different key order, different spacing, `\\/` instead of `/`, escaped unicode. The HMAC is then correct for a body nobody sent. In Express that means `express.raw()` instead of `express.json()`; in a Next.js route handler `await request.text()` before anything else.',
            ],
            [
                'icon' => 'square-2-stack',
                'title' => 'One write can produce two deliveries. That is not a double send.',
                'body' => 'Saving a meetup date recalculates the activity state of the meetup that owns it. If `is_active` or `last_event_at` change with it, a second envelope goes out for the `meetup` resource — right after the `meetup-event` one, with the next `sequence`. Both are real changes to two different records. The rule is "one delivery per changed record", never "one per request". A subscription that lists only one of the two resources sees only its half.',
            ],
            [
                'icon' => 'clock',
                'title' => 'A retry carries a new timestamp and a new signature over the same body.',
                'body' => '`X-Portal-Timestamp` is the moment of the attempt, so the signed string changes on every retry while the body stays identical. Cache nothing on the signature, and do not treat a new signature as a new event — `X-Portal-Delivery` is what stays the same. The moment of the CHANGE is `occurred_at`, inside the body.',
            ],
            [
                'icon' => 'arrow-uturn-right',
                'title' => 'A redirect is a failure, not a hop.',
                'body' => 'We never follow one, cross-host or not, and a 3xx is scored exactly like any other non-2xx: the attempt failed. If your endpoint moved, PATCH the subscription — a redirect at the old address will burn through the retry schedule and count towards the auto-disable instead.',
            ],
            [
                'icon' => 'eye-slash',
                'title' => 'The secret is shown once, and the list endpoint will not show it again.',
                'body' => 'It is generated server-side (32 random bytes) and returned in the `201` of the creation call. After that `GET`/`PATCH` omit it, because it is stored encrypted and the plaintext is gone from our side of the conversation too. The one exception is a subscription created with `reveal_secret: true`, which lets its owner read it back later. Lost it without that flag? Delete and recreate — there is no in-place rotation.',
            ],
            [
                'icon' => 'pause-circle',
                'title' => 'Nothing is queued while a subscription is pending, paused or disabled.',
                'body' => 'A delivery row is created only for a subscription that is approved, active and not auto-disabled. Everything that changes while yours is none of those never becomes a delivery at all — there is no backlog waiting for you. That window is exactly what `GET /api/changes?since=` is for, and it is the reason the very first thing a receiver should do after being approved is a catch-up.',
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
     * Die bewusst akzeptierten Luecken, ohne Beschoenigung.
     *
     * @return list<array{title:HtmlString, body:HtmlString}>
     */
    #[Computed]
    public function gaps(): array
    {
        $gaps = [
            [
                'title' => 'No in-place secret rotation',
                'body' => 'Rotating means delete and recreate, which means a new subscription id and a gap between the two. Plan the gap with a catch-up over `/api/changes`; the new subscription does not receive what happened before it existed.',
            ],
            [
                'title' => 'Only '.implode(' and ', $this->resources()).' can be subscribed',
                'body' => 'The change log carries six resources; the webhook offer is deliberately narrower, because a URL we POST to is a liability we take on per subscription. The other four are a config-level addition (`einundzwanzig.webhooks.allowed_resources`) whenever a consumer asks for one by name.',
            ],
            [
                'title' => 'No ordering guarantee',
                'body' => 'One queued job per delivery, several workers, and a retry schedule that spans hours: a delivery that failed twice can arrive after a newer one. `sequence` is strictly increasing and gap-free at the source, so a gap you can see is a gap you can fix — sort by it, deduplicate by it, use it as the cursor.',
            ],
            [
                'title' => 'No delivery log you can read',
                'body' => 'Every attempt is recorded on our side (response code, attempt count, the payload as sent), but there is no endpoint that hands it to you. If a delivery is missing, `/api/changes` answers the question "what did I not get" without asking us. `webhook:retry` is an operator command, not an API.',
            ],
            [
                'title' => 'The approval gate is manual, and so is re-enabling',
                'body' => $this->requiresApproval()
                    ? 'A new subscription starts `pending` and receives nothing until an operator approves it — portal accounts are cheap (Nostr/LNURL login) and a webhook makes this server POST to an arbitrary URL, so one manual flip per known consumer is the abuse brake. The same holds after an auto-disable: we do not resume by ourselves, the owner does (`PATCH {"active": true}`).'
                    : 'Approval is switched off on this installation, so a new subscription is live immediately. After an auto-disable we still do not resume by ourselves — the owner does (`PATCH {"active": true}`).',
            ],
            [
                'title' => 'No `truncated` flag, unlike the WebSocket',
                'body' => 'The socket drops `data` for an envelope above Reverb\'s size limit and marks it `truncated: true`. A webhook has no such limit and therefore no such flag: you always get the complete object. If you also consume the socket, keep handling `truncated` there — the two transports differ on exactly this one field.',
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
    {{-- Dekorativer Hintergrund-Glow, wie auf /docs/websockets --}}
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

        <div class="flex items-center gap-2">
            <flux:button :href="route('docs.websockets')" variant="ghost" icon="bolt" size="sm">
                WebSockets
            </flux:button>
            <flux:button :href="route('scramble.docs.ui')" variant="ghost" icon="book-open-text" size="sm">
                API reference
            </flux:button>
        </div>
    </header>

    {{-- Hero --}}
    <section class="mx-auto max-w-4xl px-6 pt-6 pb-12 text-center sm:pt-12">
        <flux:badge color="orange" size="sm" icon="arrow-up-right" class="mb-6">Webhooks</flux:badge>

        <h1 class="mx-auto max-w-3xl text-balance text-4xl font-bold tracking-tight sm:text-5xl">
            Outbound webhooks
        </h1>

        <p class="mx-auto mt-6 max-w-2xl text-pretty text-lg text-zinc-600 dark:text-zinc-300">
            Register a URL and get a signed HTTP POST for every create, update and delete of a
            meetup or a meetup date — no poller, no resident socket process, no cursor state to
            keep on your side.
        </p>
    </section>

    {{-- Zwei Wege --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-orange-500/30 bg-orange-500/[0.06] p-6 sm:p-8">
                <div class="flex items-center gap-3">
                    <flux:icon name="arrow-up-right" class="size-5 text-orange-500"/>
                    <flux:heading size="lg">The webhook — we call you</flux:heading>
                </div>
                <p class="mt-3 text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    One POST per changed record, with the full object in the body and an HMAC
                    signature in the header. Retried on a backoff schedule, at-least-once. All you
                    run is an HTTP endpoint.
                </p>
            </div>

            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/[0.06] p-6 sm:p-8">
                <div class="flex items-center gap-3">
                    <flux:icon name="shield-check" class="size-5 text-emerald-500"/>
                    <flux:heading size="lg">
                        <a href="{{ $this->changesUrl }}" class="underline decoration-emerald-500/40 underline-offset-4">GET /api/changes</a>
                        — the safety net
                    </flux:heading>
                </div>
                <p class="mt-3 text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    The same envelopes, cursor-paginated and replayable for
                    {{ $this->pruneDays }} days. Public, no token. It is how you close a gap after
                    an outage on either side, and how you find out what happened while your
                    subscription was pending or paused.
                </p>
            </div>
        </div>

        <div class="mt-4 flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
            <flux:icon name="arrow-path" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
            <p class="text-pretty leading-relaxed text-zinc-700 dark:text-zinc-200">
                <strong>Do not build only the first half.</strong>
                Deliveries are attempted only while your subscription is approved, active and not
                auto-disabled — nothing is stored up for the time it was not. A receiver that
                cannot answer "what did I miss?" with a single
                <code class="rounded bg-zinc-900/5 px-1 py-0.5 font-mono text-sm dark:bg-white/10">since=</code>
                call has an unbounded hole in it. The webhook says <em>now</em>, the endpoint says
                <em>completely</em>.
            </p>
        </div>
    </section>

    {{-- Registrierung --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">Registering a subscription</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            Four authenticated endpoints under
            <code>{{ $this->subscriptionsUrl }}</code>, or the same thing with buttons under
            <code>/&lt;country&gt;/settings/webhooks</code> in the portal. Only your own
            subscriptions are ever visible to you.
        </p>

        <pre class="mb-6 overflow-x-auto rounded-xl border border-zinc-200 bg-white p-5 text-xs leading-relaxed dark:border-white/10 dark:bg-zinc-900"><code>{{ $this->registrationExample }}</code></pre>

        <div class="mb-6 grid gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border border-amber-500/40 bg-amber-500/[0.08] p-6">
                <div class="flex items-center gap-3">
                    <flux:icon name="key" class="size-5 shrink-0 text-amber-500"/>
                    <flux:heading size="lg">The secret, once</flux:heading>
                </div>
                <p class="mt-3 text-pretty text-sm leading-relaxed text-zinc-700 dark:text-zinc-200">
                    Generated server-side, returned in the <code>201</code> below, and never again
                    afterwards — unless you register with <code>reveal_secret: true</code>, which
                    lets you read it back later. Store it before you close the terminal.
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3">
                    <flux:icon name="check-badge" class="size-5 shrink-0 text-orange-500"/>
                    <flux:heading size="lg">
                        @if ($this->requiresApproval)
                            Then it waits
                        @else
                            Live immediately
                        @endif
                    </flux:heading>
                </div>
                <p class="mt-3 text-pretty text-sm leading-relaxed text-zinc-700 dark:text-zinc-200">
                    @if ($this->requiresApproval)
                        A new subscription is <code>pending</code> and receives nothing until an
                        operator approves it. Say hello on the issue below if nothing happens —
                        and run a catch-up over <code>/api/changes</code> as your first act once
                        it is live.
                    @else
                        Approval is switched off on this installation: the subscription is
                        <code>active</code> the moment you create it. Run a catch-up over
                        <code>/api/changes</code> anyway, or your cache starts out incomplete.
                    @endif
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <div class="flex items-center gap-3">
                    <flux:icon name="lock-closed" class="size-5 shrink-0 text-orange-500"/>
                    <flux:heading size="lg">What we refuse</flux:heading>
                </div>
                <p class="mt-3 text-pretty text-sm leading-relaxed text-zinc-700 dark:text-zinc-200">
                    <code>https</code> only, and the hostname has to resolve to a public, routable
                    address — loopback, RFC1918, link-local and the cloud metadata address are
                    rejected with a <code>422</code>. Checked again at delivery time, so a
                    hostname repointed inwards later stops being delivered to.
                </p>
            </div>
        </div>

        <pre class="overflow-x-auto rounded-xl border border-zinc-200 bg-white p-5 text-xs leading-relaxed dark:border-white/10 dark:bg-zinc-900"><code>{{ $this->registrationResponse }}</code></pre>

        <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
            <code>resources</code> accepts {{ implode(' and ', $this->resources) }} today. Anything
            else is a <code>422</code> rather than a subscription that silently never fires.
        </p>
    </section>

    {{-- Header-Tabelle --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">The headers on every delivery</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            Four of them, and the body is <code>application/json</code>. This is the whole
            contract; nothing else about the request is promised.
        </p>

        <div class="overflow-x-auto rounded-2xl border border-zinc-200 dark:border-white/10">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-white/[0.06] dark:text-zinc-400">
                    <tr>
                        <th scope="col" class="px-5 py-3">Header</th>
                        <th scope="col" class="px-5 py-3">Value</th>
                        <th scope="col" class="px-5 py-3">What it is for</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white/60 dark:divide-white/10 dark:bg-white/[0.03]">
                    @foreach ($this->headers as $header)
                        <tr wire:key="header-{{ $header['name'] }}">
                            <td class="px-5 py-4 align-top font-mono font-semibold text-orange-500">{{ $header['name'] }}</td>
                            <td class="px-5 py-4 align-top font-mono text-xs break-all">{{ $header['value'] }}</td>
                            <td class="px-5 py-4 align-top text-zinc-600 dark:text-zinc-300">{!! $header['note'] !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- TypeScript --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">Verifying the signature in TypeScript</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            Copy-paste, no dependencies. The signature is
            <code>hash_hmac('sha256', timestamp + '.' + rawBody, secret)</code> in lowercase hex —
            GitHub's <code>X-Hub-Signature-256</code> idea with the timestamp mixed in against
            replay, but <strong>without</strong> the <code>sha256=</code> prefix. Or ignore the
            signature entirely: it is a proof offered, not a hurdle imposed.
        </p>
        <pre class="overflow-x-auto rounded-xl border border-zinc-200 bg-white p-5 text-xs leading-relaxed dark:border-white/10 dark:bg-zinc-900"><code>{{ $this->typescriptExample }}</code></pre>

        <div class="mt-6 flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
            <flux:icon name="shield-exclamation" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
            <div>
                <flux:heading size="lg" class="mb-2">Five rules for the secret</flux:heading>
                <ol class="list-decimal space-y-2 pl-5 text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    <li>Keep it in your own secret manager or an environment variable — never in
                        the repository, never in a log line.</li>
                    <li>Verify against the <strong>raw request body</strong>, before any JSON
                        parsing. Re-serialising and re-signing will not match.</li>
                    <li>Use a constant-time comparison: <code>crypto.timingSafeEqual()</code> in
                        Node, <code>hash_equals()</code> in PHP,
                        <code>hmac.compare_digest()</code> in Python. Never <code>==</code> on the
                        two hex strings.</li>
                    <li>Check <code>X-Portal-Timestamp</code> is recent before accepting, so a
                        captured request cannot be replayed later even with a valid signature.</li>
                    <li>Treat a compromise like a password leak: ask for the subscription to be
                        deleted and recreated rather than trying to rotate in place.</li>
                </ol>
            </div>
        </div>
    </section>

    {{-- Zustellung, Retry, Abschaltung --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">Success, retries and auto-disable</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            <strong>Success is any <code>2xx</code> within {{ $this->timeoutSeconds }} seconds.</strong>
            Everything else — a non-2xx, a redirect, a timeout, a refused connection — is a failed
            attempt. Answer first and do your work afterwards; a receiver that finishes its own
            processing before replying is the usual cause of a timeout.
        </p>

        <div class="mb-6 overflow-x-auto rounded-2xl border border-zinc-200 dark:border-white/10">
            <table class="w-full min-w-[34rem] text-left text-sm">
                <thead class="bg-zinc-50 text-xs uppercase tracking-wide text-zinc-500 dark:bg-white/[0.06] dark:text-zinc-400">
                    <tr>
                        <th scope="col" class="px-5 py-3">Attempt</th>
                        <th scope="col" class="px-5 py-3">Waits</th>
                        <th scope="col" class="px-5 py-3">After the change</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 bg-white/60 dark:divide-white/10 dark:bg-white/[0.03]">
                    @foreach ($this->retrySchedule as $step)
                        <tr wire:key="attempt-{{ $step['attempt'] }}">
                            <td class="px-5 py-3 font-mono font-semibold text-orange-500">{{ $step['attempt'] }}</td>
                            <td class="px-5 py-3 font-mono text-zinc-600 dark:text-zinc-300">{{ $step['delay'] }}</td>
                            <td class="px-5 py-3 font-mono text-zinc-600 dark:text-zinc-300">{{ $step['cumulative'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="space-y-4">
            <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <flux:icon name="x-circle" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
                <div>
                    <flux:heading size="lg" class="mb-2">{{ count($this->retrySchedule) }} attempts over {{ $this->retryWindow }}, then the delivery is failed</flux:heading>
                    <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                        After the last attempt the delivery is marked failed and the
                        subscription's <code>consecutive_failures</code> goes up by one — one
                        failed <em>delivery</em>, not one failed attempt. A single successful
                        delivery resets that counter to zero.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-4 rounded-2xl border border-amber-500/40 bg-amber-500/[0.08] p-6">
                <flux:icon name="power" class="mt-0.5 size-5 shrink-0 text-amber-500"/>
                <div>
                    <flux:heading size="lg" class="mb-2">{{ $this->autoDisableAfter }} failed deliveries in a row disable the subscription</flux:heading>
                    <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                        Counted across events, not within one event's retries. From then on
                        <strong>no further deliveries are even queued</strong>, and the gap is
                        yours to close over <code>/api/changes</code> once you are back. Re-enable
                        it yourself with <code>PATCH {{ '{"active": true}' }}</code> — which also
                        clears the failure count — or through the settings page. We do not resume
                        on our own: an endpoint that has been dead for
                        {{ $this->autoDisableAfter }} deliveries has earned a human's attention.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <flux:icon name="wrench-screwdriver" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
                <div>
                    <flux:heading size="lg" class="mb-2">Recovery: <code>webhook:retry</code></flux:heading>
                    <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                        An operator command, not an API: <code>php artisan webhook:retry &lt;id&gt;</code>
                        re-queues every delivery of one subscription that we gave up on. It
                        deliberately refuses while the subscription is pending, paused or
                        disabled — re-enabling is the owner's decision, and requeueing behind
                        their back would defeat the auto-disable. Ask for it on the issue below if
                        you need it; for a gap you can close yourself, <code>/api/changes</code> is
                        faster.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- Semantik --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">Delivery semantics</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            <strong>At-least-once.</strong> Build the receiver so a repeat costs nothing, because
            a repeat will happen — a timeout on our side after your side already committed looks
            exactly like a failure to us.
        </p>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <flux:heading size="lg" class="mb-2">Deduplicate on two keys</flux:heading>
                <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    <code>X-Portal-Delivery</code> is stable across every retry of the same
                    delivery — the natural idempotency key for the webhook path.
                    <code>sequence</code> in the body identifies the CHANGE and is the same number
                    <code>/api/changes</code> hands out, so it is the key that deduplicates across
                    both paths. If you consume both, key on <code>sequence</code>.
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <flux:heading size="lg" class="mb-2">Gap recovery</flux:heading>
                <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    Keep the highest <code>sequence</code> you have applied, persisted. A gap, a
                    restart, an auto-disable, an outage on either side — all of them are the same
                    single call:
                    <code>GET {{ $this->changesUrl }}?since=&lt;sequence&gt;</code>. Beyond
                    {{ $this->pruneDays }} days the log is pruned and the answer carries
                    <code>cursor_expired: true</code>: re-read the regular endpoints in full and
                    continue from the <code>next_since</code> of that same answer.
                </p>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <flux:heading size="lg" class="mb-2">Deletions: <code>data: null</code>, plus <code>previous</code></flux:heading>
                <p class="mb-3 text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    Nothing here is soft-deleted, so a <code>deleted</code> envelope is the only
                    place the identifiers of a gone record still exist. <code>data</code> is
                    <code>null</code> and <code>previous</code> carries what you need to
                    invalidate the right cache entry:
                </p>
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-zinc-200 dark:divide-white/10">
                        @foreach ($this->previousFields as $row)
                            <tr wire:key="previous-{{ $row['resource'] }}">
                                <td class="py-2 pr-4 font-mono text-orange-500">{{ $row['resource'] }}</td>
                                <td class="py-2 font-mono text-zinc-600 dark:text-zinc-300">{{ $row['fields'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <flux:heading size="lg" class="mb-2">Which language a tag name is in</flux:heading>
                <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                    Tags are multilingual and the envelope is written once, at the moment of the
                    change — so <code>name</code> is in the language of the request that CAUSED
                    the change, not in one you can ask for. You never have to guess which:
                    <code>name_locale</code> names it, and <code>translations</code> carries every
                    other language of the same tag. Pick from there instead of trusting
                    <code>name</code>.
                </p>
            </div>
        </div>
    </section>

    {{-- Payload --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">The payload</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            The body is the envelope from the change log, unchanged — the same bytes
            <code>GET /api/changes</code> returns, because it is built once and stored, not
            rebuilt per transport. One parser covers both. <code>data</code> follows the shape of
            the matching REST resource, so you do not need a second set of types either.
        </p>

        @foreach ($this->payloadExamples as $example)
            <div wire:key="payload-{{ $loop->index }}" class="mb-6">
                <flux:heading size="lg" class="mb-2 font-mono">{{ $example['title'] }}</flux:heading>
                <p class="mb-3 text-pretty text-sm text-zinc-600 dark:text-zinc-300">{!! $example['note'] !!}</p>
                <pre class="overflow-x-auto rounded-xl border border-zinc-200 bg-white p-5 text-xs leading-relaxed dark:border-white/10 dark:bg-zinc-900"><code>{{ $example['json'] }}</code></pre>
            </div>
        @endforeach

        <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
            <flux:icon name="user-minus" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
            <p class="text-pretty leading-relaxed text-zinc-700 dark:text-zinc-200">
                <strong>There is no author in the envelope.</strong> Who made a change is kept in
                our own column and never posted out: an outbound payload that named the acting
                user would be a public author index. If you need attribution for a record, it is
                the record's own <code>created_by</code>, nothing more.
            </p>
        </div>
    </section>

    {{-- Stolperfallen --}}
    <section class="mx-auto max-w-5xl px-6 pb-16">
        <flux:heading size="xl" class="mb-2">Six things that go wrong</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            None of these produce a useful error message on your side. That is what they have in
            common, and why they are listed here rather than left to be discovered.
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
        <flux:heading size="xl" class="mb-2">Gaps, accepted on purpose</flux:heading>
        <p class="mb-6 text-pretty text-zinc-600 dark:text-zinc-300">
            None of these are oversights. They are listed so you can decide whether they are a
            price you can pay — and every one of them is survivable in the same way:
            <code>/api/changes</code>.
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
                <flux:icon name="bolt" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
                <div>
                    <flux:heading size="lg" class="mb-2">Need it faster than a queued POST?</flux:heading>
                    <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                        The same envelopes go out over two public WebSocket channels within
                        milliseconds, without authentication — and without any of the guarantees
                        on this page. That transport, its channel names and its own accepted gaps
                        are documented at
                        <a href="{{ route('docs.websockets') }}" wire:navigate class="underline underline-offset-4">/docs/websockets</a>.
                        Both transports read the same change log, so <code>sequence</code>
                        deduplicates across all three paths.
                    </p>
                </div>
            </div>

            <div class="flex items-start gap-4 rounded-2xl border border-zinc-200 bg-white/60 p-6 dark:border-white/10 dark:bg-white/[0.03]">
                <flux:icon name="chat-bubble-left-right" class="mt-0.5 size-5 shrink-0 text-orange-500"/>
                <div>
                    <flux:heading size="lg" class="mb-2">Missing a resource, or waiting for approval?</flux:heading>
                    <p class="text-pretty leading-relaxed text-zinc-600 dark:text-zinc-300">
                        Cities, courses, course events and lecturers are in the change log but not
                        on the webhook offer; widening it is a config change, not a feature. Say
                        so — with the resource you need by name — on
                        <a href="https://github.com/HolgerHatGarKeineNode/einundzwanzig-app/issues/36"
                           class="underline underline-offset-4" target="_blank" rel="noopener">issue #36</a>.
                    </p>
                </div>
            </div>
        </div>
    </section>
</div>
