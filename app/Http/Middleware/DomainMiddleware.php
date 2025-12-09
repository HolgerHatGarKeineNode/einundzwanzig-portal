<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class DomainMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $request->getHost(); // Erkennt die aktuelle Domain (via CNAME)

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

        // App-Locale dynamisch setzen
        if (isset($domainArray[$domain])) {
            $domainConfig = $domainArray[$domain];

            // Only set session values if they are not already set (first visit)
            // This allows user language preferences to persist
            if (! session()->has('lang_country')) {
                session(['lang_country' => $domainConfig['lang_country']]);
            }

            if (! session()->has('locale')) {
                session(['locale' => $domainConfig['locale']]);
            }

            // Always set config values for the domain
            config([
                'app.name' => $domainConfig['app_name'],
                'app.domain_country' => $domainConfig['locale'],
            ]);

            // Set app locale based on user's current lang_country preference
            $currentLangCountry = session('lang_country', $domainConfig['lang_country']);
            $currentLocale = explode('-', $currentLangCountry)[0];
            App::setLocale($currentLocale);
        }

        return $next($request);
    }
}
