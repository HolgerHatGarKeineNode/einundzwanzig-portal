<?php

return [

    'open-ai' => [
        'apiKey' => env('OPEN_AI_API_KEY'),
    ],

    'horizon' => [
        'secret' => env('HORIZON_SECRET'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'twitter' => [
        'oauth' => 2,
        'client_id' => env('TWITTER_CLIENT_ID'),
        'client_secret' => env('TWITTER_CLIENT_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
    ],

    'lnbits' => [
        'url' => env('LNBITS_URL'),
        'wallet_id' => env('LNBITS_WALLET_ID'),
        'read_key' => env('LNBITS_READ_KEY'),
    ],

    'nostr' => [
        'privatekey' => env('NOSTR_PRIVATE_KEY'),
    ],

];
