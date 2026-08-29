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
        'relays' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('NOSTR_RELAYS', 'wss://nos.lol,wss://relay.damus.io'))
        ))),
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
