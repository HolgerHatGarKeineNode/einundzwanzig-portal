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
        if (isset($domainArray[$domain]['locale'])) {
            session([
                'lang_country' => $domainArray[$domain]['lang_country'],
                'locale' => $domainArray[$domain]['locale'],
            ]);
            config([
                'app.name' => $domainArray[$domain]['app_name'],
                'app.domain_country' => $domainArray[$domain]['locale'],
            ]);
            App::setLocale($domainArray[$domain]['locale']);
        }

        return $next($request);
    }
}
