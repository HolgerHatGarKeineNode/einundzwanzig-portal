<?php

return [
    'fallback' => 'en-GB',

    'allowed' => [
        'bn-BD',
        'bg-BG',
        'ca-ES',
        'cs-CZ',
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
        'cs' => ['name' => 'Čeština', 'countries' => ['cs-CZ'], 'calendar_name' => 'Jednadvacet'],
        'de' => ['name' => 'Deutsch', 'countries' => ['de-DE', 'de-AT', 'de-CH'], 'calendar_name' => 'Einundzwanzig'],
        'en' => ['name' => 'English', 'countries' => ['en-GB', 'en-US', 'en-AU', 'en-CA'], 'calendar_name' => 'Twenty-one'],
        'es' => ['name' => 'Español', 'countries' => ['es-ES', 'es-CL', 'es-CO'], 'calendar_name' => 'Veintiuno'],
        'hu' => ['name' => 'Magyar', 'countries' => ['hu-HU'], 'calendar_name' => 'Huszonegy'],
        'lv' => ['name' => 'Latviešu', 'countries' => ['lv-LV'], 'calendar_name' => 'Divdesmit viens'],
        'nl' => ['name' => 'Nederlands', 'countries' => ['nl-NL', 'nl-BE'], 'calendar_name' => 'Eenentwintig'],
        'pl' => ['name' => 'Polski', 'countries' => ['pl-PL'], 'calendar_name' => 'Dwadzieścia jeden'],
        'pt' => ['name' => 'Português', 'countries' => ['pt-PT'], 'calendar_name' => 'Vinte e um'],
    ],
];
