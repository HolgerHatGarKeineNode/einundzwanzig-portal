<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Erzwingt den OAuth-Scope "mcp:use" auf dem MCP-Endpunkt.
 *
 * Greift einheitlich für beide Guards: Sanctum-Tokens (Standard-Ability "*") und
 * Passport-OAuth-Tokens (Scope "mcp:use") erfüllen die Prüfung über tokenCan(), das
 * an das jeweilige Token-Modell delegiert. Ein Passport-Token ohne Scope wird abgelehnt.
 */
class EnsureMcpScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && method_exists($user, 'tokenCan') && ! $user->tokenCan('mcp:use')) {
            abort(Response::HTTP_FORBIDDEN, 'Das Token besitzt nicht den erforderlichen Scope "mcp:use".');
        }

        return $next($request);
    }
}
