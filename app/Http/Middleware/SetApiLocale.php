<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces English on the public API.
 *
 * The API documentation (`/docs/api`) is English, so the responses have to match:
 * validation messages and every `__()` string in the API path resolve against
 * `lang/en.json` and `lang/en/validation.php` instead of the app default (`de`).
 *
 * Why a dedicated middleware: `DomainMiddleware`, which derives the locale from the
 * session, only runs in the `web` group, so API requests silently fell back to
 * `config('app.locale')`. This touches neither the web UI nor the MCP server — the
 * MCP routes (`routes/ai.php`) register their own middleware chain and never join
 * the `api` group.
 *
 * Only the TRANSLATOR is switched, deliberately not `App::setLocale()`: the latter
 * also writes `config('app.locale')`, and the slug generation of Meetup, City, Venue
 * and Lecturer reads exactly that value as its fallback
 * (`usingLanguage(Cookie::get('lang', config('app.locale')))`). API clients send no
 * `lang` cookie, so an app-wide switch would transliterate differently on every
 * write: a PATCH on a meetup named "Nürnberg" rewrote its slug from `nuernberg` to
 * `nurnberg` — measured — and that slug is the public URL under
 * `{country:code}/meetup/{meetup:slug}`. Switching the translator alone keeps
 * `config('app.locale')` untouched and limits the effect to text output.
 *
 * The locale is not restored after the request: `Illuminate\Routing\Pipeline` renders
 * aborts inside the pipeline, so a restore would be harmless there, but the request
 * shutdown discards the state anyway. Under Octane (not installed today) reset it in
 * the RequestReceived hook.
 */
class SetApiLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        Lang::setLocale('en');

        return $next($request);
    }
}
