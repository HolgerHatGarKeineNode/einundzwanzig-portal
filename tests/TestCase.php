<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Client\Factory as HttpClientFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\ParallelTesting;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Symfony's Request::create() — what every $this->get()/postJson() call in
         * Laravel's test HTTP client builds requests with — injects a synthetic
         * 'en-us,en;q=0.5' Accept-Language default on every request that doesn't
         * specify one (Request.php's own hardcoded $server defaults). A real request
         * built from PHP's actual superglobals never carries that value unless a
         * browser sent it. Left in place, any test that hits a route through
         * DomainMiddleware's first-visit Accept-Language resolution silently looks
         * like an English-speaking visitor, regardless of what the test intended.
         *
         * Tests that care about the header set it explicitly via withHeaders() or
         * withServerVariables() — same as the existing convention in
         * MeetupEventTagsApiTest.
         */
        $this->serverVariables['HTTP_ACCEPT_LANGUAGE'] = '';

        $this->preventStrayHttpRequests();
        $this->isolateSushiCache();
    }

    /*
     * An unfaked outgoing request does not fail a test, it simply leaves the
     * machine (Nominatim, a webhook target, the association's nostr.json) and
     * the only symptom is a slow or order-dependent test. With the flag armed
     * it becomes a StrayRequestException naming the URL, i.e. a red test.
     *
     * Armed per test, not once at load time: the flag is instance state on the
     * HTTP client factory, and Laravel rebuilds the container (and so the
     * factory) for every test. The rebinding() hook re-arms a factory that a
     * test installs through Http::swap(), which would otherwise be unarmed.
     *
     * Limits: code that opens its own socket (the Nostr relay WebSocket) never
     * touches this facade, and a call site that swallows the exception
     * (catch Throwable) turns a refused request back into an empty result —
     * the guard stops the request, it does not always report it.
     * tests/Feature/Support/HttpStrayRequestTest.php proves it by effect.
     */
    private function preventStrayHttpRequests(): void
    {
        Http::preventStrayRequests();

        $this->app->rebinding(
            HttpClientFactory::class,
            fn ($app, HttpClientFactory $factory) => $factory->preventStrayRequests(),
        );
    }

    /*
     * Sushi (the country list) keeps an on-disk SQLite cache under
     * storage/framework/cache. SushiCacheIntegrityTest deliberately truncates,
     * deletes and re-dates that very file; in a parallel run every other worker
     * reads it at the same moment and fails with "no such table: countries"
     * (measured: 4 of 2585 tests red at 24 workers, 0 serially).
     *
     * Each worker (and the serial run) therefore gets its own cache directory,
     * which Sushi fills on first use. The tests still exercise the real
     * mechanism, only on a file nobody else reads.
     */
    private function isolateSushiCache(): void
    {
        $directory = storage_path('framework/cache/testing-'.(ParallelTesting::token() ?: 'serial'));

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        config()->set('sushi.cache-path', $directory);
    }
}
