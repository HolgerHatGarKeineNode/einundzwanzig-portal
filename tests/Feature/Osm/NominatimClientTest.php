<?php

use App\Services\Osm\NominatimClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    NominatimClient::resetThrottle();
    Cache::flush();
});

function nominatimRow(array $overrides = []): array
{
    return array_merge([
        'osm_type' => 'node',
        'osm_id' => 12345,
        'name' => 'Bitcoin Bar',
        'display_name' => 'Bitcoin Bar, Hauptstraße 1, Praha, Česko',
        'lat' => '50.0755381',
        'lon' => '14.4378005',
        'importance' => 0.42,
        'category' => 'amenity',
    ], $overrides);
}

it('normalises a search result into the stored fields', function () {
    Http::fake(['*' => Http::response([nominatimRow()])]);

    $hit = (new NominatimClient(minIntervalMs: 0))->search('Bitcoin Bar Praha')->first();

    expect($hit['osm_type'])->toBe('node')
        ->and($hit['osm_id'])->toBe(12345)
        ->and($hit['osm_name'])->toBe('Bitcoin Bar')
        ->and($hit['osm_address'])->toContain('Hauptstraße 1')
        ->and($hit['osm_lat'])->toBe(50.0755381);
});

it('sends an identifying user agent, never the library default', function () {
    // The policy rejects stock library headers outright.
    Http::fake(['*' => Http::response([])]);

    (new NominatimClient(minIntervalMs: 0))->search('irgendwas');

    Http::assertSent(function ($request): bool {
        $agent = $request->header('User-Agent')[0] ?? '';

        return $agent !== '' && str_contains($agent, 'einundzwanzig');
    });
});

it('caches results so a repeated search costs no request', function () {
    Http::fake(['*' => Http::response([nominatimRow()])]);

    $client = new NominatimClient(minIntervalMs: 0);
    $client->search('Bitcoin Bar Praha');
    $client->search('Bitcoin Bar Praha');

    Http::assertSentCount(1);
});

it('returns empty instead of throwing when the service fails', function () {
    Http::fake(['*' => Http::response('gateway down', 503)]);

    expect((new NominatimClient(minIntervalMs: 0))->search('Bitcoin Bar'))->toBeEmpty();
});

it('returns empty instead of throwing when the connection dies', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    expect((new NominatimClient(minIntervalMs: 0))->search('Bitcoin Bar'))->toBeEmpty();
});

it('does not call the service for a query that is too short', function () {
    Http::fake(['*' => Http::response([nominatimRow()])]);

    expect((new NominatimClient(minIntervalMs: 0))->search('ab'))->toBeEmpty();

    Http::assertNothingSent();
});

it('falls back to the first address segment when a place has no name', function () {
    Http::fake(['*' => Http::response([nominatimRow(['name' => ''])])]);

    $hit = (new NominatimClient(minIntervalMs: 0))->search('Hauptstraße 1 Praha')->first();

    expect($hit['osm_name'])->toBe('Bitcoin Bar');
});

it('narrows the search by country when asked', function () {
    Http::fake(['*' => Http::response([])]);

    (new NominatimClient(minIntervalMs: 0))->search('Hauptbahnhof', 'CZ');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'countrycodes=cz'));
});

it('looks a known osm reference back up', function () {
    Http::fake(['*' => Http::response([nominatimRow()])]);

    $hit = (new NominatimClient(minIntervalMs: 0))->lookup('node', 12345);

    expect($hit['osm_name'])->toBe('Bitcoin Bar');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'osm_ids=N12345'));
});

it('rejects an unknown osm type without calling out', function () {
    Http::fake(['*' => Http::response([nominatimRow()])]);

    expect((new NominatimClient(minIntervalMs: 0))->lookup('planet', 1))->toBeNull();

    Http::assertNothingSent();
});

it('uses a far wider interval for bulk work than for interactive use', function () {
    // The policy allows 1/second interactively but only 4/minute for scripts.
    $bulk = NominatimClient::forBulk();
    $interactive = new NominatimClient;

    $read = fn (NominatimClient $c): int => (new ReflectionProperty($c, 'minIntervalMs'))->getValue($c);

    expect($read($bulk))->toBe(15_000)
        ->and($read($interactive))->toBeGreaterThanOrEqual(1_000);
});

it('actually waits between two uncached requests', function () {
    Http::fake(['*' => Http::response([])]);

    $client = new NominatimClient(minIntervalMs: 300);

    $started = microtime(true);
    $client->search('erste anfrage');
    $client->search('zweite anfrage');
    $elapsed = (microtime(true) - $started) * 1000;

    expect($elapsed)->toBeGreaterThanOrEqual(280);
});
