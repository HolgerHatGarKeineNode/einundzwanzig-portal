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
    | Tag icons
    |--------------------------------------------------------------------------
    |
    | The only icon names a tag may carry. Flux resolves `<flux:icon name="x">`
    | by delegating to a Blade component `flux::icon.x`, and a name it cannot
    | resolve is not a blank space — it is an uncaught exception that takes the
    | whole page down:
    |
    |   Flux component [icon.coin] does not exist.
    |
    | The seed vocabulary was written against Font Awesome (`coin`, `beer-mug`,
    | `chalkboard-user`, `user-secret`, …) while Flux ships Heroicons. Fifteen of
    | the ninety-one production tags carried such a name. Nothing broke only
    | because no screen rendered `tags.icon` at all; the first one that did would
    | have thrown a 500 for every event offered one of those tags.
    |
    | So this list is a whitelist, not documentation. Every output site resolves
    | through resources/views/livewire/tags/partials/icon.blade.php, which falls
    | back to `tag` for anything not listed here. Deliberately NOT a try/catch:
    | a catch swallows typos, and a vocabulary that silently accepts typos is not
    | a vocabulary. A value outside this list survives in the database and is
    | shown in the moderation screen as "<name> — not resolvable", so an old
    | record stays visible and fixable instead of exploding.
    |
    | Names must exist under vendor/livewire/flux/stubs/resources/views/flux/icon
    | (or resources/views/flux/icon for icons published by hand).
    | TagIconVocabularyTest checks every entry against those two directories, so
    | a typo here fails a test rather than a page.
    |
    */

    'tag_icons' => [
        // Format of the gathering
        'microphone',
        'presentation-chart-bar',
        'chat-bubble-left-right',
        'chat-bubble-oval-left-ellipsis',
        'users',
        'user-group',
        'film',
        'musical-note',
        'video-camera',
        'megaphone',
        'ticket',
        'calendar-days',
        'clock',

        // Level and material
        'academic-cap',
        'rocket-launch',
        'book-open',
        'newspaper',
        'document-text',
        'puzzle-piece',
        'light-bulb',
        'trophy',

        // Place and reach
        'map-pin',
        'globe-europe-africa',
        'building-storefront',
        'building-library',
        'home',
        'truck',
        'shopping-bag',

        // Money
        'circle-stack',
        'banknotes',
        'wallet',
        'currency-euro',
        'receipt-percent',
        'bolt',

        // Custody, privacy, law
        'key',
        'lock-closed',
        'shield-check',
        'eye-slash',
        'finger-print',
        'scale',

        // Technology
        'server',
        'cpu-chip',
        'code-bracket',
        'command-line',
        'wrench-screwdriver',
        'beaker',

        // Open-ended
        'fire',
        'heart',
        'hand-raised',
        'sparkles',
        'star',
        'tag',
    ],

    /*
    |--------------------------------------------------------------------------
    | Icons offered before the moderator types
    |--------------------------------------------------------------------------
    |
    | The icon picker holds fifty-two names; showing all of them at rest turns a
    | choice into a search. This subset is what the vocabulary actually uses
    | today, so the resting list answers "what do we already say" rather than
    | "what could you possibly pick". Typing reveals the full list — the same
    | resting/searching split the tag picker itself uses.
    |
    | Must be a subset of tag_icons; TagIconVocabularyTest checks that too.
    |
    */

    'tag_icons_common' => [
        'tag',
        'microphone',
        'presentation-chart-bar',
        'chat-bubble-left-right',
        'chat-bubble-oval-left-ellipsis',
        'users',
        'user-group',
        'film',
        'academic-cap',
        'rocket-launch',
        'circle-stack',
        'bolt',
        'key',
        'eye-slash',
        'server',
        'building-storefront',
    ],

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
    | EINUNDZWANZIG board — webhook approval admin gate
    |--------------------------------------------------------------------------
    |
    | Who may reach /admin/webhooks and approve or revoke a pending webhook
    | subscription (Issue #36's require_approval gate, Issue #40's admin UI
    | over it). A separate copy from `tag_editors` above on purpose — the two
    | happen to be the same people today, but one gates tag creation and the
    | other gates outbound-HTTP approval, and they should be free to diverge
    | without a change to one silently touching the other.
    |
    | Hardcoded and copied on 2026-09-01 from einundzwanzig-verein,
    | config/einundzwanzig/config.php ('current_board') — deliberately NOT
    | VereinMeetupGate::vereinMemberHexes()'s verein.einundzwanzig.space API
    | call, whose fail-soft-to-empty-set behaviour on a network error would
    | make this gate silently inaccessible to the whole board instead of
    | loudly broken. That repository remains the authoritative list — this is
    | a copy, so it needs updating by hand whenever the board changes. See
    | App\Support\BoardGate.
    |
    */

    'board_members' => [
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
    | Outbound webhooks for change-log consumers
    |--------------------------------------------------------------------------
    |
    | Issue #36: consumers who cannot run a resident WebSocket process or a
    | poller register a URL and get an HTTP POST for every matching change,
    | dispatched from ChangeRecorder::record() (the same chokepoint as the
    | broadcast). See App\Support\Webhooks\WebhookDispatcher.
    |
    | `require_approval` is the abuse brake: portal accounts are cheap
    | (Nostr/LNURL login) and a webhook makes the server POST to a URL an
    | account holder chose, so a new subscription is inert until an operator
    | approves it — today that is a direct database action, not an endpoint.
    |
    | `allowed_resources` restricts subscriptions to a subset of
    | ChangeRecorder::resourceNames(): only meetup and meetup-event are
    | offered initially, though the recorder already logs six resources.
    | Widening this later is a config change, not a migration.
    |
    | `backoff_seconds` is both the retry schedule AND the attempt count: one
    | initial attempt plus one retry per entry. Five entries below means six
    | attempts in total, satisfying the "at least 5 attempts" requirement
    | with room for the job's own first try.
    |
    | `auto_disable_after` counts failed deliveries (each one already
    | exhausted every retry above), not failed HTTP attempts — a subscription
    | that fails ten separate events in a row is disabled and stays disabled
    | until its owner re-enables it via PATCH.
    |
    */

    'webhooks' => [
        'require_approval' => env('WEBHOOKS_REQUIRE_APPROVAL', true),
        'allowed_resources' => ['meetup', 'meetup-event'],
        'timeout_seconds' => 10,
        'backoff_seconds' => [60, 300, 1800, 7200, 21600],
        'auto_disable_after' => 10,
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
