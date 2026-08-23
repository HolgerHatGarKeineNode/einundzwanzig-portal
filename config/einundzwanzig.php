<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tag locales
    |--------------------------------------------------------------------------
    |
    | The languages a tag can carry a name and description in. This list is the
    | single source of truth for the tag picker, which renders one hidden alias
    | per locale so a Czech organiser can find a tag that only exists in German.
    |
    | Deliberately NOT read from config/translatable.php: that file belongs to
    | the unused astrotomic package, lists only en/fr/es, and is read by nothing.
    | Iterating over it would silently narrow the picker to three languages —
    | no error, no failing test, just a search that quietly stops working.
    |
    | Must stay in sync with the directories under lang/. TagLocalesTest guards it.
    |
    */

    'tag_locales' => ['cs', 'de', 'en', 'es', 'hu', 'lv', 'nl', 'pl', 'pt'],

    /*
    |--------------------------------------------------------------------------
    | Tag editors
    |--------------------------------------------------------------------------
    |
    | Nostr npubs allowed to create tags directly. Everyone else may still
    | suggest one — it is usable on their own event immediately but stays out of
    | other people's suggestions until a tag editor approves it.
    |
    | Seeded with the board of Einundzwanzig e.V., copied on 2026-08-17 from
    | einundzwanzig-verein, config/einundzwanzig/config.php ('current_board').
    | That repository remains the authoritative list — this is a copy, so it
    | needs updating by hand whenever the board changes. Extra editors who are
    | not board members can simply be appended here.
    |
    */

    'tag_editors' => [
        'npub1pt0kw36ue3w2g4haxq3wgm6a2fhtptmzsjlc2j2vphtcgle72qesgpjyc6',
        'npub1gvqkjccl9urg93svaw60jqkk3ux8r3ycl5t3rlvc9uzjeu0agfuss8x8qy',
        'npub10t8npnmqhpwx9w8k232kess7gqtdlr6kqjemdzf8jnughwqd0gwsez0924',
        'npub1r8343wqpra05l3jnc4jud4xz7vlnyeslf7gfsty7ahpf92rhfmpsmqwym8',
        'npub17fqtu2mgf7zueq2kdusgzwr2lqwhgfl2scjsez77ddag2qx8vxaq3vnr8y',
        'npub1v4lgwjv7qfn3t7qjscpsgz9vqvspf6hecdp2ckgp0dz89uqn5slsgrhw3p',
        'npub14r770s5wrqpm8jmzur5arnm9aum9x0wasaxwczael54xhjggl7ws5lygc6',
    ],

    /*
    |--------------------------------------------------------------------------
    | Countries requiring tags on events
    |--------------------------------------------------------------------------
    |
    | Lower-case values of countries.code. Events belonging to a country in this
    | list cannot be saved without at least one tag; everywhere else tags stay
    | optional so existing records and other communities are unaffected.
    |
    | Requested for Czechia in issue #4, built as a list so other communities can
    | opt in without a code change.
    |
    */

    'tags_required_countries' => ['cz'],

    /*
    |--------------------------------------------------------------------------
    | Change log for API consumers
    |--------------------------------------------------------------------------
    |
    | Issue #29: every create, update and delete on one of the six public API
    | resources is written to `api_changes`, so a consumer can resync from a
    | cursor instead of diffing a fresh export against an old cache.
    |
    | `enabled` is the kill switch. Off means the recorder returns before it
    | builds anything — no resource is resolved, no row is written, and nothing
    | queues up for later. Turning it back on does NOT backfill; the gap stays a
    | gap, which is exactly why the switch is here and not a runtime guess.
    |
    | Off by default in the test environment (see phpunit.xml). Not for speed
    | alone: DatabaseSeeder creates over 250 records, and every one of them would
    | otherwise resolve a JsonResource and write a row that no test asked for.
    | Tests that DO test the log turn it on themselves.
    |
    | For a single block — a seeder, an import command — there is
    | ChangeRecorder::muted(fn () => ...), which restores the previous state even
    | when the block throws.
    |
    | `prune_days` is how long a row survives. It bounds the table and, with it,
    | how far back /api/changes can resync. Shortening it silently shortens that
    | window for every consumer, so it belongs in the docs, not just here.
    |
    */

    'change_log' => [
        'enabled' => env('CHANGE_LOG_ENABLED', true),
        'prune_days' => (int) env('CHANGE_LOG_PRUNE_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Realtime — wohin ein KONSUMENT verbindet
    |--------------------------------------------------------------------------
    |
    | `public_host` ist der oeffentliche Hostname des WebSocket-Servers, so wie
    | ihn /docs/websockets ausweist. Er hat einen eigenen Schluessel, weil ihn
    | sonst nichts kennt:
    |
    |  - `REVERB_HOST` (config/broadcasting.php) sagt, wohin die ANWENDUNG
    |    publiziert. In Produktion steht dort 127.0.0.1:8080 — der lokale
    |    Daemon, damit ein Broadcast nicht als TLS-Roundtrip nach draussen und
    |    wieder herein laeuft. Als Verbindungsangabe fuer einen Konsumenten
    |    waere das die Anweisung, sich mit sich selbst zu verbinden.
    |  - `app.url` ist die Portal-Domain. Sie stimmte, solange Reverb als
    |    Pfad-Proxy unter derselben Domain hing. Seit P5 laeuft der Server ueber
    |    Forges Reverb-Integration auf einer EIGENEN Subdomain
    |    (ws.portal.einundzwanzig.space) mit eigenem Zertifikat — dieselbe
    |    Domain anzunehmen, waere schlicht falsch.
    |
    | Genau diese Verwechslung — Publish-Ziel gegen Verbindungsziel — ist der
    | Grund fuer den eigenen Schluessel. Leer heisst: Host aus `app.url`, was
    | lokal (`composer run dev`) das Richtige ist.
    |
    | Nur Host, ohne Schema. Ein abweichender Port darf angehaengt werden
    | (`ws.example.test:8443`); ohne Angabe gilt der Standardport des Schemas.
    | Das Schema selbst folgt `app.url`: http lokal, https in Produktion.
    |
    */

    'realtime' => [
        'public_host' => env('REVERB_PUBLIC_HOST'),
    ],

];
