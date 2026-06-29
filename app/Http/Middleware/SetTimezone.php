<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetTimezone
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = $request->user()?->timezone ?: 'Europe/Berlin';

        config([
            'app.timezone' => $timezone,
            'app.user-timezone' => $timezone,
        ]);

        return $next($request);
    }
}
