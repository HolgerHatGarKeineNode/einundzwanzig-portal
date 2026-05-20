<?php

return [
    'fallback' => 'en-GB',

    'allowed' => [
        'bn-BD',
        'bg-BG',
        'ca-ES',
        'da-DA',
        'de-AT',
        'de-CH',
        'de-DE',
        'en-AU',
        'en-CA',
        'en-CH',
        'en-GB',
        'en-US',
        'el-GR',
        'es-CL',
        'es-CO',
        'es-ES',
        'fr-BE',
        'fr-CA',
        'fr-CH',
        'fr-FR',
        'hu-HU',
        'id-ID',
        'it-CH',
        'it-IT',
        'lt-LT',
        'lv-LV',
        'nl-BE',
        'nl-NL',
        'ps-AF',
        'pt-PT',
        'pl-PL',
        'ru-RU',
    ],

    'lang_switcher_middleware' => ['web'],

    'lang_switcher_uri' => 'change_lang_country',

    'fallback_based_on_current_locale' => false,

    'languages' => [
        'de' => ['name' => 'Deutsch', 'countries' => ['de-DE', 'de-AT', 'de-CH']],
        'en' => ['name' => 'English', 'countries' => ['en-GB', 'en-US', 'en-AU', 'en-CA']],
        'es' => ['name' => 'Español', 'countries' => ['es-ES', 'es-CL', 'es-CO']],
        'hu' => ['name' => 'Magyar', 'countries' => ['hu-HU']],
        'lv' => ['name' => 'Latviešu', 'countries' => ['lv-LV']],
        'nl' => ['name' => 'Nederlands', 'countries' => ['nl-NL', 'nl-BE']],
        'pl' => ['name' => 'Polski', 'countries' => ['pl-PL']],
        'pt' => ['name' => 'Português', 'countries' => ['pt-PT']],
    ],
];
