<?php

use App\Http\Middleware\DomainMiddleware;
use App\Http\Middleware\EnforcePkceS256;
use App\Http\Middleware\SetTimezone;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Features\SupportFileUploads\MissingFileUploadsTraitException;
use Livewire\Features\SupportLifecycleHooks\DirectlyCallingLifecycleHooksNotAllowedException;
use Livewire\Mechanisms\HandleComponents\CorruptComponentPayloadException;
use Stefro\LaravelLangCountry\Middleware\LangCountrySession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            DomainMiddleware::class,
            LangCountrySession::class,
            SetTimezone::class,
            // Erzwingt PKCE-S256 auf dem Passport-Authorize-Endpunkt (oauth/authorize).
            EnforcePkceS256::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isStaleLivewireAsset = function (Throwable $e, ?Request $request): bool {
            if (! $request instanceof Request) {
                return false;
            }

            if (! preg_match('#^livewire-[a-f0-9]+/(?:css|js)/#', $request->path())) {
                return false;
            }

            $stalePatterns = [
                'does not have a style source',
                'does not have a global style source',
                'does not have a script source',
                'Style file not found',
                'Global style file not found',
                'Script file not found',
            ];

            foreach ($stalePatterns as $pattern) {
                if (str_contains($e->getMessage(), $pattern)) {
                    return true;
                }
            }

            return false;
        };

        $isStaleCompiledView = function (Throwable $e): bool {
            if (! $e instanceof FileNotFoundException) {
                return false;
            }

            return str_contains($e->getMessage(), '/storage/framework/views/');
        };

        $isMissingFileUploadsTrait = function (Throwable $e): bool {
            return $e instanceof MissingFileUploadsTraitException;
        };

        $isLivewireExploitProbe = function (Throwable $e): bool {
            // Deserialization/RCE bots probe the `livewire/update` endpoint by invoking
            // protected lifecycle hooks or PHP magic methods to reach gadget chains.
            // Livewire safely rejects these calls; the rejection is the bot signature,
            // so we silence the resulting noise instead of reporting it as a 500.
            if ($e instanceof DirectlyCallingLifecycleHooksNotAllowedException) {
                return true;
            }

            if ($e instanceof MethodNotFoundException) {
                return (bool) preg_match('/Public method \[__/', $e->getMessage());
            }

            return false;
        };

        $isMalformedLivewirePropertyUpdate = function (Throwable $e): bool {
            // Bots replay `livewire/update` with a mutated snapshot that sets public
            // properties the target component never declared (e.g. `value` on `welcome`).
            // Livewire rejects the update; in production this is pure bot noise, so we
            // silence it. Locally we let it surface, so a genuinely undeclared property
            // binding is still caught during development.
            return $e instanceof PublicPropertyNotFoundException && ! app()->isLocal();
        };

        /*
         * TokenMismatchException steht in Laravels `internalDontReport`, und dieser
         * Filter greift VOR den hier registrierten Callbacks — ohne stopIgnoring()
         * wuerde der report()-Callback unten fuer sie nie laufen. Die Entscheidung,
         * ob etwas protokolliert wird, faellt dann dort: angemeldet ja, anonym nein.
         */
        $exceptions->stopIgnoring(TokenMismatchException::class);

        /*
         * Die Ausnahmen, die als 419 enden und von Haus aus keine Spur hinterlassen.
         * TokenMismatchException steht in Laravels internalDontReport,
         * CorruptComponentPayloadException schalten wir selbst stumm.
         */
        $isSilencedFourNineteen = function (Throwable $e): bool {
            return $e instanceof CorruptComponentPayloadException
                || $e instanceof TokenMismatchException;
        };

        /*
         * Die Namen der Livewire-Komponenten aus dem Request-Rumpf.
         *
         * Fail-soft: ein kaputter oder fehlender Snapshot ist genau der Fall, den wir
         * protokollieren wollen — daran darf das Protokollieren nicht scheitern.
         *
         * @return array<int, string>
         */
        $livewireComponentNames = function (Request $request): array {
            try {
                return collect($request->input('components', []))
                    ->pluck('snapshot')
                    ->map(fn ($snapshot) => is_string($snapshot) ? json_decode($snapshot, true) : null)
                    ->pluck('memo.name')
                    ->filter()
                    ->values()
                    ->all();
            } catch (Throwable) {
                return [];
            }
        };

        $exceptions->report(function (Throwable $e) use ($isStaleLivewireAsset, $isStaleCompiledView, $isMissingFileUploadsTrait, $isLivewireExploitProbe, $isMalformedLivewirePropertyUpdate, $isSilencedFourNineteen, $livewireComponentNames) {
            if ($isStaleLivewireAsset($e, request())) {
                return false;
            }

            if ($isStaleCompiledView($e)) {
                return false;
            }

            if ($isMissingFileUploadsTrait($e)) {
                return false;
            }

            if ($isLivewireExploitProbe($e)) {
                return false;
            }

            if ($isMalformedLivewirePropertyUpdate($e)) {
                return false;
            }

            /*
             * Ein 419 ist unsichtbar, und das war das eigentliche Problem.
             *
             * Sowohl `TokenMismatchException` (CSRF) als auch
             * `CorruptComponentPayloadException` enden als 419 — die erste steht in
             * Laravels `internalDontReport`, die zweite haben wir hier selbst
             * stummgeschaltet. Als am 2026-08-22 ein Nutzer ein Event nicht speichern
             * konnte (Issue #18, mehrere 419 auf /livewire/update), stand im
             * Produktionslog zu diesem Zeitpunkt keine einzige Zeile. Ohne Spur laesst
             * sich nicht einmal entscheiden, WELCHE der beiden Ursachen es war.
             *
             * Die Bedingung war zuerst `auth()->check()` — und die schloss genau den
             * Fall aus, den sie finden sollte. Ist die Ursache eine verlorene Session,
             * ist der Request per Definition NICHT angemeldet: kein Session-Cookie,
             * das der Server aufloesen kann, also auch kein Nutzer. Am 2026-08-23
             * meldete iBobik um 12:20 UTC einen Fehlversuch; das Logging war seit
             * 12:03 live und schrieb trotzdem keine Zeile.
             *
             * Das Kriterium ist deshalb jetzt: **hat der Browser ein Session-Cookie
             * mitgeschickt?** Wer eins mitbringt, war irgendwann auf der Seite — das
             * ist ein echter Besucher, ob seine Session serverseitig noch gilt oder
             * nicht. Bots, die `/livewire/update` mit mutierten Snapshots beharken,
             * schicken keins; die Flut, wegen der die Unterdrueckung eingebaut wurde,
             * bleibt draussen.
             */
            if ($isSilencedFourNineteen($e)) {
                $request = request();

                $hasSessionCookie = $request->cookies->has(config('session.cookie'));

                if (! $hasSessionCookie && ! auth()->hasUser()) {
                    return false;
                }

                Log::warning('419 mit Session-Cookie', [
                    'exception' => $e::class,
                    // null heisst: Cookie da, Session serverseitig weg — genau der
                    // Verdachtsfall aus Issue #18.
                    'user_id' => auth()->hasUser() ? auth()->id() : null,
                    'session_started' => $request->hasSession() && $request->session()->isStarted(),
                    'path' => $request->path(),
                    // Bei Livewire steht die eigentliche Komponente im Snapshot, nicht
                    // im Pfad — ohne sie weiss man nur "irgendwo im Portal".
                    'livewire_components' => $livewireComponentNames($request),
                    'referer' => $request->headers->get('referer'),
                    'user_agent' => $request->userAgent(),
                ]);

                return false;
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isStaleLivewireAsset, $isStaleCompiledView, $isMissingFileUploadsTrait, $isLivewireExploitProbe, $isMalformedLivewirePropertyUpdate) {
            if ($isStaleLivewireAsset($e, $request)) {
                return response('', 404);
            }

            if ($isStaleCompiledView($e)) {
                return response('', 503)->header('Retry-After', '5');
            }

            if ($isMissingFileUploadsTrait($e)) {
                return response('', 400);
            }

            if ($isLivewireExploitProbe($e)) {
                return response('', 400);
            }

            if ($isMalformedLivewirePropertyUpdate($e)) {
                return response('', 400);
            }

            return null;
        });
    })->create();
