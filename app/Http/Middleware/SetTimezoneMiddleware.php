<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class SetTimezoneMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale(Cookie::get('lang') ?: config('app.locale'));
        if ($request->user()
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
