<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Nostr-Publisher (NIP-52 Kalender-Events, Issue #34)
    |--------------------------------------------------------------------------
    |
    | Eigene Identitaet fuer die vom Portal signierten kind 31923/31924 Events —
    | getrennt vom bestehenden kind:1-Textnote-Versand (der laeuft ueber `noscl`
    | und dessen eigenen, serverseitig konfigurierten Schluessel, siehe NostrTrait).
    | nsec- oder Hex-Format, beides akzeptiert `nostr:publish-calendar`.
    |
    */
    'nostr' => [
        'publisher_key' => env('NOSTR_PUBLISHER_NSEC'),

        /*
         * Every relay the portal WRITES calendar events to and reads RSVPs from. Read it
         * through App\Support\NostrRelays, never directly.
         *
         * The default is the relay list the publishing key advertises in its own NIP-65
         * kind 10002 (as signed on 2026-11-17): the association's relay first, then six
         * large public strfry relays whose NIP-11 shows no auth, no payment and no
         * restricted writes, and which were measured to store kind 31923/31924. Until
         * 2026-11-17 the default was two relays, of which only one was in that list.
         *
         * nostr.einundzwanzig.space reports `restricted_writes: true` (nostr-rs-relay):
         * it refuses the portal key until that key is on its pubkey whitelist. It is in
         * the list anyway — a refusal is recorded per relay and costs one connection —
         * and it is never REQUIRED: see `publish_quorum`.
         */
        'relays' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env(
                'NOSTR_RELAYS',
                'wss://nostr.einundzwanzig.space,wss://nos.lol,wss://relay.primal.net,wss://relay.damus.io,wss://nostr.mom,wss://relay.nostr.net,wss://offchain.pub'
            ))
        ))),

        /*
         * How many relays must answer `OK true` for an event before the record counts as
         * published. Two, not one: "one relay is enough" is what let 557 of 684 upcoming
         * dates be marked published while neither configured relay held them (measured
         * 2026-11-17). Capped at the number of configured relays.
         */
        'publish_quorum' => (int) env('NOSTR_PUBLISH_QUORUM', 2),
    ],

    /*
     * Server-zu-Server Bearer-Token für den vereinsmitglied-gegateten Meetup-Endpunkt
     * (GET /api/verein/gated-meetups). Wird gegen den Authorization: Bearer <token>
     * Header geprüft. In Prod-.env als VEREIN_GATE_TOKEN setzen.
     */
    /*
    |--------------------------------------------------------------------------
    | Nominatim (OpenStreetMap geocoding)
    |--------------------------------------------------------------------------
    |
    | The public instance enforces a strict usage policy: max 1 request/second
    | (4 per MINUTE for bulk scripts), mandatory client-side caching, and a
    | meaningful User-Agent — a library's stock header is rejected outright.
    |
    | Point `url` at a self-hosted instance to lift the rate limits.
    |
    */
    'nominatim' => [
        'url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT'),
    ],

    'verein_gate' => [
        'token' => env('VEREIN_GATE_TOKEN'),
    ],

];
