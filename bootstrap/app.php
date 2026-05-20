<?php

use App\Http\Middleware\DomainMiddleware;
use App\Http\Middleware\SetTimezone;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Livewire\Features\SupportFileUploads\MissingFileUploadsTraitException;
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

        $exceptions->report(function (Throwable $e) use ($isStaleLivewireAsset, $isStaleCompiledView, $isMissingFileUploadsTrait) {
            if ($isStaleLivewireAsset($e, request())) {
                return false;
            }

            if ($isStaleCompiledView($e)) {
                return false;
            }

            if ($isMissingFileUploadsTrait($e)) {
                return false;
            }
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isStaleLivewireAsset, $isStaleCompiledView, $isMissingFileUploadsTrait) {
            if ($isStaleLivewireAsset($e, $request)) {
                return response('', 404);
            }

            if ($isStaleCompiledView($e)) {
                return response('', 503)->header('Retry-After', '5');
            }

            if ($isMissingFileUploadsTrait($e)) {
                return response('', 400);
            }

            return null;
        });
    })->create();
