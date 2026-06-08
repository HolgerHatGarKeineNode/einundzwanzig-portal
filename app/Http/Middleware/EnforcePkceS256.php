<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Erzwingt PKCE mit S256 auf dem OAuth-Authorize-Endpunkt.
 *
 * Die Discovery-Metadaten bewerben ausschließlich S256, der zugrunde liegende
 * league/oauth2-server akzeptiert standardmäßig aber auch das schwächere "plain".
 * Diese Middleware lehnt jede Authorize-Anfrage mit einer anderen Methode als S256 ab,
 * sodass das tatsächliche Verhalten der beworbenen Metadaten entspricht (OAuth 2.1).
 */
class EnforcePkceS256
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('oauth/authorize')
            && $request->filled('code_challenge')
            && $request->input('code_challenge_method', 'plain') !== 'S256') {
            abort(Response::HTTP_BAD_REQUEST, 'Es wird ausschließlich die PKCE-Methode "S256" unterstützt.');
        }

        return $next($request);
    }
}
