<?php

if (! function_exists('route_with_country')) {
    function route_with_country(string $name, array $parameters = [], bool $absolute = true): string
    {
        if (! isset($parameters['country'])) {
            $country = request()->route('country') ?? 'de';
        } else {
            $country = str(session('lang_country', 'de'))->after('-')->lower();
        }
        $parameters = ['country' => $country] + $parameters;

        return route($name, $parameters, $absolute);
    }
}

if (! function_exists('get_domain_attributes')) {
    function get_domain_attributes(): array
    {
        $langCountry = session('lang_country', 'de-DE');

        // Check if specific domain image exists
        if (! file_exists(public_path('img/domains/'.$langCountry.'.jpg'))) {
            $langCountry = 'de-DE';
        }

        $southAmericanCountries = [
            'ar-AR', // Argentina
            'bo-BO', // Bolivia
            'br-BR', // Brazil
            'cl-CL', // Chile
            'co-CO', // Colombia
            'ec-EC', // Ecuador
            'gy-GY', // Guyana
            'py-PY', // Paraguay
            'pe-PE', // Peru
            'sr-SR', // Suriname
            'uy-UY', // Uruguay
            've-VE', // Venezuela
        ];

        $author = 'einundzwanzig';
        $image = asset('img/domains/'.$langCountry.'.jpg');
        // Use LAT image for South American countries
        if (in_array($langCountry, $southAmericanCountries, true)) {
            $image = asset('img/domains/lat.png');
            $author = 'veintiuno';
            $twitter = 'veintiunolat';
        }

        $countryAuthorMapping = [
            'de-DE' => 'einundzwanzig',
            'de-AT' => 'einundzwanzig',
            'de-CH' => 'einundzwanzig',
            'en-GB' => 'twenty-one',
            'en-US' => 'twenty-one',
            'es-ES' => 'veintiuno',
            'nl-NL' => 'eenentwintig',
            'pl-PL' => 'dwadzieścia',
            'hu-HU' => 'huszonegy',
        ];

        $countryTwitterMapping = [
            'de-DE' => '_einundzwanzig_',
            'de-AT' => '_einundzwanzig_',
            'de-CH' => '_einundzwanzig_',
            'en-GB' => '_einundzwanzig_',
            'en-US' => '_einundzwanzig_',
            'nl-NL' => '_Eenentwintig_',
            'pl-PL' => '21BitcoinPolska',
            'hu-HU' => 'HUSZONEGYworld',
        ];

        $author = $countryAuthorMapping[$langCountry] ?? 'einundzwanzig';
        $twitter = $countryTwitterMapping[$langCountry] ?? '_einundzwanzig_';

        // Der Markenname gehört zur Domain, nicht zur Nutzersprache: wer auf
        // portal.eenentwintig.net liest, ist auf dem EENENTWINTIG Portaal —
        // auch mit deutscher Oberfläche. DomainMiddleware setzt app.name je
        // Host; für Hosts ohne Eintrag greift APP_NAME.
        $siteName = config('app.name');

        return [
            'image' => $image,
            'author' => $author,
            'twitter' => $twitter,
            'siteName' => $siteName,
        ];
    }
}
