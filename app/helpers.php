<?php

if (! function_exists('domain_region_for')) {
    /**
     * Die Region dieser Domain — aber nur, solange das Land noch dazu passt.
     *
     * `portal.bitcoindiana.org` liefert `in` (Indiana), damit ein Besucher auf
     * `/us/in/meetups` landet statt im ganzen Land (Issue #6). Die Region haengt an
     * der DOMAIN, das Land dagegen an der Sprachwahl der Sitzung — und die kann der
     * Besucher jederzeit umstellen.
     *
     * Genau da sitzt der Riegel: wer auf Bitcoin Diana auf Deutsch umschaltet, hat
     * `de` als Land, und `/de/in/meetups` waere ein 404 (Indiana ist kein deutsches
     * Bundesland; ein unbekannter Regionscode antwortet ausdruecklich mit 404 statt
     * mit einer leeren Liste). Passt das Land nicht mehr zum Domain-Land, gibt es
     * hier deshalb null — der Aufrufer faellt dann auf die Landesroute zurueck.
     */
    function domain_region_for(string $country): ?string
    {
        $region = config('app.domain_region');

        if (! is_string($region) || $region === '') {
            return null;
        }

        return $country === config('app.domain_country') ? $region : null;
    }
}

if (! function_exists('active_region_for')) {
    /**
     * Die Region, die fuer einen Link in dieses Land gilt.
     *
     * Zwei Quellen, in dieser Reihenfolge:
     *
     *  1. **Die laufende Route.** Wer ueber einen geteilten Link auf `/us/nc/cities`
     *     kommt, bleibt in North Carolina — auch auf einer Domain, die Indiana
     *     bevorzugt. Ohne diesen Vorrang wuerde die Navigation ihn beim naechsten Klick
     *     nach `/us/in/…` werfen, und das ist kein hypothetischer Fall: von sechs
     *     US-Staedten liegen vier ausserhalb Indianas (NC, AL, SC, Stand 2026-08-24).
     *  2. **Die Domain.** `portal.bitcoindiana.org` fuehrt seine Besucher nach Indiana,
     *     solange die Route selbst keine Region nennt.
     *
     * DER WAECHTER, ohne den das ein 404-Generator waere: Die Region der Route gilt nur,
     * wenn das Ziel-Land dasselbe ist wie das Land dieser Route. Sonst entstuende auf
     * `/us/nc/cities` beim Sprung nach Deutschland ein `/de/nc/…` — und ein Regionscode,
     * den das Land nicht kennt, antwortet mit 404 statt mit einer leeren Liste. Genau
     * diese Kombination hat P2 gerade im Laenderwaehler geschlossen; sie darf hier nicht
     * durch die Hintertuer zurueckkommen.
     *
     * Verglichen wird klein geschrieben: ein Laendercode kann als `US` hereinkommen, und
     * ein strikter Vergleich haette die Region dann still fallen lassen — eine
     * Zufallsrettung, kein Schutz.
     */
    function active_region_for(string $country): ?string
    {
        $route = request()->route();
        $ausRoute = $route?->parameter('region');
        $landDerRoute = $route?->parameter('country');

        if (is_string($ausRoute) && $ausRoute !== ''
            && is_string($landDerRoute)
            && mb_strtolower($landDerRoute) === mb_strtolower($country)) {
            return $ausRoute;
        }

        return domain_region_for($country);
    }
}

if (! function_exists('country_or_region_route')) {
    /**
     * Baut das Standard-Ziel einer Listen-Route und nimmt die Region mit, wenn die
     * Domain eine hat.
     *
     * Regionsvarianten gibt es nur fuer Meetups, Karte und Staedte
     * (`meetups.index-region`, `meetups.map-region`, `cities.index-region`); alles
     * andere hat keine, und dort bleibt es bei der Landesroute. Uebergeben wird
     * darum der Name der LANDES-Route, und `-region` wird nur angehaengt, wenn es
     * die Variante wirklich gibt — sonst baut ein Tippfehler eine Route, die es
     * nicht gibt, und die Seite stirbt an einem Detail, das niemand vermutet.
     *
     * @param  array<string, mixed>  $parameters
     */
    function country_or_region_route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $country = $parameters['country']
            ?? request()->route('country')
            ?? str(session('lang_country', config('app.domain_country', 'de')))
                ->after('-')
                ->lower()
                ->value();

        $region = active_region_for($country);

        if ($region !== null && app('router')->has($name.'-region')) {
            return route($name.'-region', [...$parameters, 'country' => $country, 'region' => $region], $absolute);
        }

        return route($name, [...$parameters, 'country' => $country], $absolute);
    }
}

if (! function_exists('route_with_country')) {
    function route_with_country(string $name, array $parameters = [], bool $absolute = true): string
    {
        /*
         * Reihenfolge: ausdruecklich uebergeben, dann die laufende Route, dann die
         * Sprachwahl der Sitzung.
         *
         * Die beiden ersten Zweige waren vertauscht — ein uebergebenes 'country' wurde
         * verworfen und statt dessen aus der Sitzung gebaut, waehrend ein fehlendes
         * still auf 'de' fiel. Bei einem Livewire-Update heisst die Route
         * `livewire.update` und traegt gar kein 'country': jeder Redirect nach dem
         * Speichern landete deshalb in Deutschland, egal welches Land der Nutzer
         * gerade pflegte (Issue #28). Der harte Rueckfall ist damit weg — die
         * Sitzung weiss es besser als eine Konstante.
         */
        $country = $parameters['country']
            ?? request()->route('country')
            ?? str(session('lang_country', config('app.domain_country', 'de')))
                ->after('-')
                ->lower()
                ->value();

        $parameters['country'] = $country;

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
