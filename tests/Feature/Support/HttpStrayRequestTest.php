<?php

declare(strict_types=1);

use Illuminate\Http\Client\Factory as HttpClientFactory;
use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

/*
 * The stray-request guard from tests/TestCase.php, proven by its effect.
 *
 * Asserting Http::preventingStrayRequests() would only restate that a setter
 * was called. What has to hold is that an unfaked request ENDS the test with an
 * exception right where it was fired, instead of leaving the machine.
 */

it('turns an unfaked outgoing request into a failure instead of a network call', function () {
    expect(fn () => Http::get('https://nominatim.openstreetmap.org/search?q=Berlin'))
        ->toThrow(
            StrayRequestException::class,
            'Attempted request to [https://nominatim.openstreetmap.org/search?q=Berlin] without a matching fake.',
        );
});

it('leaves a request the test did fake completely untouched', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['ok' => true], 200)]);

    expect(Http::get('https://nominatim.openstreetmap.org/search?q=Berlin')->json())->toBe(['ok' => true]);
});

it('still refuses a host the test forgot to fake', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['ok' => true], 200)]);

    expect(fn () => Http::get('https://verein.einundzwanzig.space/api/members/x'))
        ->toThrow(StrayRequestException::class);
});

it('refuses everything after Http::fake([]) while still recording', function () {
    Http::fake([]);

    expect(fn () => Http::post('https://example.com/hook', ['a' => 1]))
        ->toThrow(StrayRequestException::class);
});

/*
 * Http::swap(new Factory) hands the facade a factory that has never been armed;
 * the rebinding() hook in TestCase re-arms it. Without that hook this test
 * would perform a real request.
 */
it('survives a test swapping the HTTP factory out from under it', function () {
    Http::swap(new HttpClientFactory);

    expect(fn () => Http::get('https://example.com/'))->toThrow(StrayRequestException::class);
});

it('keeps a fake registered after a swap working', function () {
    Http::swap(new HttpClientFactory);
    Http::fake(['example.com/*' => Http::response(['id' => 2], 200)]);

    expect(Http::get('https://example.com/x')->json())->toBe(['id' => 2]);
});
