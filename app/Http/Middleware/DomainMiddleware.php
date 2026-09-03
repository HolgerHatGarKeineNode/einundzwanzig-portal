<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Stefro\LaravelLangCountry\Services\PreferredLanguage;
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
                'timezone' => 'Europe/Berlin',
            ],
            'portal.eenentwintig.net' => [
                'locale' => 'nl',
                'lang_country' => 'nl-NL',
                'app_name' => 'EENENTWINTIG Portaal',
                'timezone' => 'Europe/Amsterdam',
            ],
            'portal.huszonegy.world' => [
                'locale' => 'hu',
                'lang_country' => 'hu-HU',
                'app_name' => 'HUSZONEGY Portál',
                'timezone' => 'Europe/Budapest',
            ],
            'portal.dwadziesciajeden.pl' => [
                'locale' => 'pl',
                'lang_country' => 'pl-PL',
                'app_name' => 'DWADZIEŚCIA JEDEN Portal',
                'timezone' => 'Europe/Warsaw',
            ],
            /*
             * Die erste Domain, bei der Sprache und Land AUSEINANDERFALLEN — und die
             * erste mit einer Region (Issue #6, bitcoindiana.org).
             *
             * Bei allen Domains darueber ist der Sprachcode zufaellig auch der
             * Laendercode (de/de, pl/pl, hu/hu, nl/nl), weshalb `locale` unten
             * jahrelang als Land durchging, ohne aufzufallen. Hier waere das Ergebnis
             * `/en/meetups` — eine Route, die es nicht gibt. Deshalb `country`
             * ausdruecklich.
             */
            'portal.bitcoindiana.org' => [
                'locale' => 'en',
                'lang_country' => 'en-US',
                'country' => 'us',
                'region' => 'in',
                'app_name' => 'Bitcoin Indiana',
                'timezone' => 'America/Indiana/Indianapolis',
            ],

            'pl.localhost' => [
                'locale' => 'pl',
                'lang_country' => 'pl-PL',
                'app_name' => 'DWADZIEŚCIA JEDEN Portal',
                'timezone' => 'Europe/Warsaw',
            ],
            'hu.localhost' => [
                'locale' => 'hu',
                'lang_country' => 'hu-HU',
                'app_name' => 'HUSZONEGY Portál',
                'timezone' => 'Europe/Budapest',
            ],
        ];

        $domainConfig = $domainArray[$domain] ?? $domainArray[self::FALLBACK_DOMAIN];

        /*
         * Resolved only on a real first visit (neither key is in the session yet). An
         * explicit prior choice, an already-guessed value from an earlier request, or an
         * authenticated account's stored preference (applied at login by the package's
         * UserAuthenticated listener, before this request) all populate `lang_country`
         * beforehand and are never overridden here.
         *
         * This still only ever writes to the session, never to `users.lang_country` — that
         * is what keeps issue #18 fixed: LangCountrySession finds both keys already set and
         * never runs its own guess-and-persist branch (see the class doc above). A value
         * only becomes permanent through an explicit choice (ApplyChosenLanguageAfterLogin).
         */
        $browserLangCountry = session()->has('lang_country')
            ? null
            : $this->resolveFromAcceptLanguage($request);

        // Nur beim ersten Besuch setzen, damit eine getroffene Wahl bestehen bleibt.
        if (! session()->has('lang_country')) {
            session(['lang_country' => $browserLangCountry ?? $domainConfig['lang_country']]);
        }

        if (! session()->has('locale')) {
            session(['locale' => $browserLangCountry !== null
                ? explode('-', $browserLangCountry)[0]
                : $domainConfig['locale']]);
        }

        /*
         * `country` faellt auf `locale` zurueck — fuer die vier Domains, bei denen
         * beides denselben Wert traegt, aendert sich damit nichts. `region` ist null,
         * solange eine Domain keine nennt; erst dann bauen die Standard-Ziele einen
         * Pfad der Form `/{country}/{region}/…`.
         */
        config([
            'app.name' => $domainConfig['app_name'],
            'app.domain_country' => $domainConfig['country'] ?? $domainConfig['locale'],
            'app.domain_region' => $domainConfig['region'] ?? null,
            'app.domain_timezone' => $domainConfig['timezone'] ?? 'UTC',
        ]);

        $currentLangCountry = session('lang_country', $domainConfig['lang_country']);
        App::setLocale(explode('-', $currentLangCountry)[0]);

        return $next($request);
    }

    /**
     * Matches Accept-Language against the lang_country values LangCountry actually
     * supports (config('lang-country.allowed')), most-preferred first. Returns null when
     * the header is missing or names nothing supported, so the caller keeps today's
     * domain default instead of the package's own generic fallback.
     */
    private function resolveFromAcceptLanguage(Request $request): ?string
    {
        $preferred = new PreferredLanguage($request->server('HTTP_ACCEPT_LANGUAGE'));

        return $preferred->findExactMatchForFourCharsOrReturnNull()
            ?? $preferred->findFirstMatchBasedOnOnlyTheLangChars();
    }
}
