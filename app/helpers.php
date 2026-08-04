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

        /*
         * Spanischsprachiges Lateinamerika teilt sich ein Motiv und die
         * veintiuno-Marke. Nur Länder, die auch in config/lang-country.php
         * unter 'allowed' stehen — alles andere kann nie in lang_country
         * landen und wäre toter Code. Kommt ein Land dazu, gehört es in
         * beide Listen.
         */
        $latinAmerican = [
            'es-CL', // Chile
            'es-CO', // Kolumbien
        ];

        if (in_array($langCountry, $latinAmerican, true)) {
            // Vor dem Bild-Fallback: lat.png ist kein .jpg, die Prüfung unten
            // würde die Erkennung sonst auf de-DE zurückwerfen.
            $image = asset('img/domains/lat.png');
        } else {
            if (! file_exists(public_path('img/domains/'.$langCountry.'.jpg'))) {
                $langCountry = 'de-DE';
            }

            $image = asset('img/domains/'.$langCountry.'.jpg');
        }

        $countryAuthorMapping = [
            'de-DE' => 'einundzwanzig',
            'de-AT' => 'einundzwanzig',
            'de-CH' => 'einundzwanzig',
            'en-GB' => 'twenty-one',
            'en-US' => 'twenty-one',
            'es-ES' => 'veintiuno',
            'es-CL' => 'veintiuno',
            'es-CO' => 'veintiuno',
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
            'es-CL' => 'veintiunolat',
            'es-CO' => 'veintiunolat',
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
