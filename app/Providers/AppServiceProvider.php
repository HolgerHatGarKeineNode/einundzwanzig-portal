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

        $this->documentRealtimeTransports();

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
     * Verweist aus der OpenAPI-Beschreibung auf die zwei Konsumenten-Seiten, die
     * Scramble nicht selbst erzeugen kann.
     *
     * ZWEI VERSCHIEDENE FAELLE, deshalb zwei verschiedene Wege im selben Transformer:
     *
     *  - WEBSOCKETS: die Kanaele sind keine Laravel-Routen, es gibt also keinen Tag,
     *    den Scramble aus einer `#[Group]` bauen koennte — und ein `webhooks`-Objekt
     *    gibt der Generator nicht her ({@see OpenApi::toArray()} schreibt sechs feste
     *    Schluessel). Der Tag wird deshalb hier ANGEHAENGT.
     *  - WEBHOOKS: die Abo-Endpunkte SIND Routen, `WebhookSubscriptionController`
     *    traegt `#[Group(name: 'Webhooks')]`, und {@see AddDocumentTags} legt den Tag
     *    bereits an — nur ohne Beschreibung. Der vorhandene Tag wird deshalb
     *    ERGAENZT statt ein zweiter gleichen Namens angelegt (das Dokument haette
     *    sonst zwei Reiter 'Webhooks'). Die Beschreibung steht hier und nicht als
     *    `description:` am Attribut, damit beide Doku-Verweise an einer Stelle
     *    stehen und nicht einer im Controller versteckt ist.
     *
     * BEWUSST ueber `afterOpenApiGenerated()` und NICHT ueber `Scramble::configure()`
     * mit `->expose(...)`: ein String-Argument an `expose()` ueberschreibt die
     * Routen-Registrierung und loescht dabei die Routennamen `scramble.docs.ui` und
     * `scramble.docs.document`, auf die `tests/Feature/Api/ApiDocsAccessTest.php`
     * baut. `afterOpenApiGenerated()` haengt lediglich einen Document-Transformer
     * hinten an die Liste — hinten, damit er nach {@see AddDocumentTags}
     * laeuft, das `$document->tags` komplett neu setzt und ein frueher angehaengtes
     * Element wieder verwerfen wuerde. Genau das ist auch die Bedingung dafuer, dass
     * der Webhooks-Tag hier ueberhaupt schon existiert.
     */
    protected function documentRealtimeTransports(): void
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

            foreach ($document->tags as $tag) {
                if ($tag->name === 'Webhooks' && $tag->description === null) {
                    $tag->description = <<<'MARKDOWN'
                    Register a URL here and every create, update and delete of a meetup or a
                    meetup date arrives as a signed HTTP POST — the same envelope
                    `GET /api/changes` returns, so one parser covers both.

                    The endpoints below manage the subscription. The delivery contract is not an
                    operation in this document and cannot be generated from one: the headers
                    (`X-Portal-Event`, `X-Portal-Delivery`, `X-Portal-Timestamp`,
                    `X-Portal-Signature`), the HMAC verification with a copy-paste TypeScript
                    snippet, the retry schedule and auto-disable, at-least-once semantics with
                    deduplication, gap recovery and the deletion `previous` semantics are
                    documented at [/docs/webhooks](/docs/webhooks).
                    MARKDOWN;
                }
            }
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
