<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Server-zu-Server Bearer-Auth für den vereinsmitglied-gegateten Meetup-Endpunkt.
 *
 * Prüft den `Authorization: Bearer <token>`-Header gegen
 * config('services.verein_gate.token'). Keine Sanctum-User-Session — der Endpunkt
 * wird von einem vertrauenswürdigen Backend (Nostr-Client) aufgerufen. Fehlt der
 * Header, ist er falsch, oder ist serverseitig kein Token konfiguriert, wird mit
 * 401 abgelehnt (hashvergleich in konstanter Zeit gegen Timing-Angriffe).
 */
class VereinGateToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.verein_gate.token');
        $provided = $request->bearerToken();

        if (! is_string($expected) || $expected === '' || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Ungültiges oder fehlendes Bearer-Token.');
        }

        return $next($request);
    }
}
