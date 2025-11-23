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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->user()
            && $timezone = $request->user()->timezone
        ) {
            config([
                'app.timezone' => $timezone,
                'app.user-timezone' => $timezone,
            ]);

            return $next($request);
        }
        config([
            'app.timezone' => 'Europe/Berlin',
            'app.user-timezone' => 'Europe/Berlin',
        ]);

        return $next($request);
    }
}
