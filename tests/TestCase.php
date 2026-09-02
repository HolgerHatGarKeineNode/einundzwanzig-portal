<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

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
    }
}
