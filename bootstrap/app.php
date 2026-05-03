<?php

use App\Http\Middleware\DomainMiddleware;
use App\Http\Middleware\SetTimezone;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Stefro\LaravelLangCountry\Middleware\LangCountrySession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            DomainMiddleware::class,
            LangCountrySession::class,
            SetTimezone::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! preg_match('#^livewire-[a-f0-9]+/(?:css|js)/#', $request->path())) {
                return null;
            }

            $message = $e->getMessage();

            $stalePatterns = [
                'does not have a style source',
                'does not have a global style source',
                'does not have a script source',
                'Style file not found',
                'Global style file not found',
                'Script file not found',
            ];

            foreach ($stalePatterns as $pattern) {
                if (str_contains($message, $pattern)) {
                    return response('', 404);
                }
            }

            return null;
        });
    })->create();
