<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class DomainMiddleware
{
    /**
     * Der Rueckfall fuer jede Domain, die unten nicht steht.
     *
     * Ohne ihn lief fuer localhost, eine Partner-Domain per CNAME oder einen
     * Vorschau-Host nichts von alledem — und die naechste Middleware,
     * LangCountrySession, fand eine leere Session vor und riet die Sprache aus
     * HTTP_ACCEPT_LANGUAGE. Schlimmer: beim ersten Login schreibt sie den geratenen
     * Wert ungefragt in users.lang_country, und ab da stellt der Login-Listener des
     * Pakets die Sprache jedes Mal wieder darauf zurueck. So entsteht ein Konto, das
     * hartnaeckig auf en-US zurueckspringt, obwohl das nie jemand gewaehlt hat.
     */
    private const FALLBACK_DOMAIN = 'portal.einundzwanzig.space';

    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->getHost(); // Detects the current domain (via CNAME)

        // domains
        $domainArray = [
            'portal.einundzwanzig.space' => [
                'locale' => 'de',
                'lang_country' => 'de-DE',
                'app_name' => 'EINUNDZWANZIG Portal',
            ],
            'portal.eenentwintig.net' => [
                'locale' => 'nl',
                'lang_country' => 'nl-NL',
                'app_name' => 'EENENTWINTIG Portaal',
            ],
            'portal.huszonegy.world' => [
                'locale' => 'hu',
                'lang_country' => 'hu-HU',
                'app_name' => 'HUSZONEGY Portál',
            ],
            'portal.dwadziesciajeden.pl' => [
                'locale' => 'pl',
                'lang_country' => 'pl-PL',
                'app_name' => 'DWADZIEŚCIA JEDEN Portal',
            ],

            'pl.localhost' => [
                'locale' => 'pl',
                'lang_country' => 'pl-PL',
                'app_name' => 'DWADZIEŚCIA JEDEN Portal',
            ],
            'hu.localhost' => [
                'locale' => 'hu',
                'lang_country' => 'hu-HU',
                'app_name' => 'HUSZONEGY Portál',
            ],
        ];

        /*
         * Faellt die Domain durch (localhost, eine Partner-Domain per CNAME, ein
         * Vorschau-Host), lief bisher nichts von alledem — und die naechste Middleware,
         * LangCountrySession, fand eine leere Session vor und riet die Sprache aus
         * HTTP_ACCEPT_LANGUAGE. Schlimmer: beim ersten Login schreibt sie den geratenen
         * Wert ungefragt in users.lang_country, und ab da stellt der Login-Listener des
         * Pakets die Sprache jedes Mal wieder darauf zurueck. Genau so entsteht ein
         * Konto, das hartnaeckig auf en-US zurueckspringt, obwohl niemand das je
         * gewaehlt hat.
         *
         * Der Default des Portals ist deshalb der Rueckfall, nicht der Browser-Header.
         */
        $domainConfig = $domainArray[$domain] ?? $domainArray[self::FALLBACK_DOMAIN];

        // Nur beim ersten Besuch setzen, damit eine getroffene Wahl bestehen bleibt.
        if (! session()->has('lang_country')) {
            session(['lang_country' => $domainConfig['lang_country']]);
        }

        if (! session()->has('locale')) {
            session(['locale' => $domainConfig['locale']]);
        }

        config([
            'app.name' => $domainConfig['app_name'],
            'app.domain_country' => $domainConfig['locale'],
        ]);

        $currentLangCountry = session('lang_country', $domainConfig['lang_country']);
        App::setLocale(explode('-', $currentLangCountry)[0]);

        return $next($request);
    }
}
