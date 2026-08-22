<?php

use App\Models\Country;
use App\Services\Osm\NominatimClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    NominatimClient::resetThrottle();
    Cache::flush();
});

function osmCountryRow(array $overrides = []): array
{
    return array_merge([
        'osm_type' => 'relation',
        'osm_id' => 51477,
        'name' => 'Deutschland',
        'display_name' => 'Deutschland',
        'lat' => '51.1638175',
        'lon' => '10.4478313',
        'category' => 'boundary',
        'extratags' => ['wikidata' => 'Q183', 'wikipedia' => 'de:Deutschland'],
    ], $overrides);
}

it('fills the empty fields of a country from its boundary relation', function () {
    Http::fake(['*' => Http::response([osmCountryRow()])]);

    // latitude/longitude ausdruecklich leeren: die Factory setzt sie, und der Befehl
    // fuellt bewusst nur Leeres — sonst pruefte dieser Test die Factory.
    $country = Country::factory()->create([
        'code' => 'de', 'name' => 'Germany', 'english_name' => 'Germany',
        'latitude' => null, 'longitude' => null,
    ]);

    $this->artisan('countries:enrich-from-osm', ['--code' => ['de'], '--interval-ms' => 0])
        ->assertSuccessful();

    $country->refresh();

    expect($country->osm_type)->toBe('relation')
        ->and($country->osm_id)->toBe(51477)
        ->and($country->wikidata)->toBe('Q183')
        ->and($country->wikipedia)->toBe('de:Deutschland')
        ->and($country->osm_url)->toBe('https://www.openstreetmap.org/relation/51477')
        ->and((float) $country->latitude)->toBe(51.1638175);
});

it('never overwrites a value that is already there', function () {
    // Ohne diese Regel waere jeder Lauf ein Risiko fuer von Hand gepflegte Korrekturen.
    Http::fake(['*' => Http::response([osmCountryRow()])]);

    $country = Country::factory()->create([
        'code' => 'de',
        'name' => 'Germany',
        'latitude' => 1.234,
        'wikidata' => 'Q-von-Hand',
    ]);

    $this->artisan('countries:enrich-from-osm', ['--code' => ['de'], '--interval-ms' => 0])
        ->assertSuccessful();

    $country->refresh();

    expect((float) $country->latitude)->toBe(1.234)
        ->and($country->wikidata)->toBe('Q-von-Hand')
        // Was leer war, wurde trotzdem gefuellt.
        ->and($country->osm_id)->toBe(51477);
});

it('writes nothing when several boundary relations match', function () {
    // Lieber nichts als das Falsche — eine falsch gewaehlte Relation faellt niemandem
    // auf, bis jemand der Karte nicht mehr traut.
    Http::fake(['*' => Http::response([
        osmCountryRow(),
        osmCountryRow(['osm_id' => 99999, 'name' => 'Deutschland (anders)']),
    ])]);

    $country = Country::factory()->create(['code' => 'de', 'name' => 'Germany']);

    $this->artisan('countries:enrich-from-osm', ['--code' => ['de'], '--interval-ms' => 0])
        ->expectsOutputToContain('2 Grenzrelationen')
        ->assertSuccessful();

    expect($country->fresh()->osm_id)->toBeNull();
});

it('ignores hits that are not boundary relations', function () {
    // "Georgia" trifft sonst den US-Bundesstaat oder eine gleichnamige Strasse.
    Http::fake(['*' => Http::response([
        osmCountryRow(['category' => 'place', 'osm_type' => 'node']),
        osmCountryRow(['category' => 'highway', 'osm_type' => 'way']),
    ])]);

    $country = Country::factory()->create(['code' => 'ge', 'name' => 'Georgia']);

    $this->artisan('countries:enrich-from-osm', ['--code' => ['ge'], '--interval-ms' => 0])
        ->expectsOutputToContain('kein Treffer')
        ->assertSuccessful();

    expect($country->fresh()->osm_id)->toBeNull();
});

it('skips countries that already have a reference', function () {
    Http::fake(['*' => Http::response([osmCountryRow()])]);

    Country::factory()->create(['code' => 'de', 'name' => 'Germany', 'osm_type' => 'relation', 'osm_id' => 51477]);

    $this->artisan('countries:enrich-from-osm', ['--code' => ['de'], '--interval-ms' => 0])
        ->expectsOutputToContain('Nichts zu tun')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('changes nothing on a dry run', function () {
    Http::fake(['*' => Http::response([osmCountryRow()])]);

    $country = Country::factory()->create(['code' => 'de', 'name' => 'Germany']);

    $this->artisan('countries:enrich-from-osm', ['--code' => ['de'], '--dry-run' => true, '--interval-ms' => 0])
        ->assertSuccessful();

    expect($country->fresh()->osm_id)->toBeNull();
});

it('honours the limit so a first run can stay small', function () {
    Http::fake(['*' => Http::response([osmCountryRow()])]);

    Country::factory()->create(['code' => 'de', 'name' => 'Aaa']);
    Country::factory()->create(['code' => 'at', 'name' => 'Bbb']);

    $this->artisan('countries:enrich-from-osm', ['--limit' => 1, '--interval-ms' => 0])
        ->assertSuccessful();

    expect(Country::whereNotNull('osm_id')->count())->toBe(1);
});
