<?php

namespace App\Providers;

use App\Support\Carbon;
use Dedoc\Scramble\DocumentTransformers\AddDocumentTags;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Tag;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Nightwatch\Facades\Nightwatch;
use Laravel\Nightwatch\Http\Middleware\Sample;
use Laravel\Passport\Passport;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Date::use(
            Carbon::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        Gate::define('viewApiDocs', fn (?Authenticatable $user = null): bool => true);

        $this->documentWebSocketChannels();

        // OAuth-2.1-Flow des MCP-Servers (Claude.ai Web-Connector).
        Passport::authorizationView(fn ($parameters) => view('mcp.authorize', $parameters));

        // Kurze Access-Token-Lebensdauer mit Refresh-Rotation begrenzt den Schaden eines
        // geleakten Tokens (öffentliche PKCE-Clients ohne Client-Secret). Passport-Default
        // wäre sonst 1 Jahr für Access- UND Refresh-Token.
        Passport::tokensExpireIn(now()->addHours(8));
        Passport::refreshTokensExpireIn(now()->addDays(14));

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/livewire/update', $handle)
                ->middleware(['web', 'throttle:livewire', Sample::rate(0)]);
        });

        Nightwatch::user(fn (Authenticatable $user) => [
            'name' => $user->name,
        ]);

        Event::listen(function (DiagnosingHealth $event) {
            Nightwatch::dontSample();
        });

        Model::preventLazyLoading(app()->environment('local'));
    }

    /**
     * Traegt die WebSocket-Kanaele als eigenen Tag in die OpenAPI-Beschreibung ein.
     *
     * Die Kanaele sind keine Laravel-Routen, Scramble kann sie also nicht generieren —
     * und ein `webhooks`-Objekt gibt der Generator nicht her ({@see OpenApi::toArray()}
     * schreibt sechs feste Schluessel). Der Tag ist deshalb der einzige Weg, in der
     * Referenz selbst auf /docs/websockets zu zeigen, statt nur in der Einleitung.
     *
     * BEWUSST ueber `afterOpenApiGenerated()` und NICHT ueber `Scramble::configure()`
     * mit `->expose(...)`: ein String-Argument an `expose()` ueberschreibt die
     * Routen-Registrierung und loescht dabei die Routennamen `scramble.docs.ui` und
     * `scramble.docs.document`, auf die `tests/Feature/Api/ApiDocsAccessTest.php`
     * baut. `afterOpenApiGenerated()` haengt lediglich einen Document-Transformer
     * hinten an die Liste — hinten, damit er nach {@see AddDocumentTags}
     * laeuft, das `$document->tags` komplett neu setzt und ein frueher angehaengtes
     * Element wieder verwerfen wuerde.
     */
    protected function documentWebSocketChannels(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $document): void {
            $document->tags[] = new Tag(
                'WebSockets',
                <<<'MARKDOWN'
                Two public WebSocket channels — `portal` (every change of every resource) and
                `meetup-events` (meetup dates only) — carry the same envelope as
                `GET /api/changes`, within milliseconds and without authentication.

                They are not HTTP routes, so they have no operations in this document.
                Channel names, event names, the payload contract, a working TypeScript client
                and the accepted gaps are documented at
                [/docs/websockets](/docs/websockets).
                MARKDOWN
            );
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('calendar', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Generous backstop for the shared `/livewire/update` endpoint. A single
        // active user stays far below this: the only sustained generator is the
        // login page's `wire:poll.4s` at ~15 req/min, plus interaction bursts.
        // 120/min leaves headroom for several users behind one NAT while still
        // capping abusive replay/scan traffic. Keyed by the real client IP
        // (trustProxies('*') resolves X-Forwarded-For).
        RateLimiter::for('livewire', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
