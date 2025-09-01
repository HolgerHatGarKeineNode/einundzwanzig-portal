<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders()
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(RouteServiceProvider::HOME);

        $middleware->validateCsrfTokens(except: [
            'horizon/*',
            'auth/auth47-callback',
        ]);

        $middleware->web(\App\Http\Middleware\SetTimezoneMiddleware::class);

        $middleware->throttleApi();
        $middleware->api(\App\Http\Middleware\SetTimezoneMiddleware::class);

        $middleware->group('nova-web', [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetTimezoneForNovaMiddleware::class,
        ]);

        $middleware->group('translation', [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\SetTimezoneMiddleware::class,
            'can:translate',
        ]);

        $middleware->alias([
            'needMeetup' => \App\Http\Middleware\NeedMeetupMiddleware::class,
            'verified' => \App\Http\Middleware\CustomEnsureEmailVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
