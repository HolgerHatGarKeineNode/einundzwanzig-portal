<?php

use App\Models\Country;

it('keeps returning a bare array, not a data wrapper', function () {
    /*
     * GET /countries antwortet seit jeher mit einem nackten Array. Die Umstellung auf
     * eine JsonResource haette daraus ohne `$wrap = null` ein {"data": [...]} gemacht —
     * und jeden bestehenden Client gebrochen. Dieser Test ist das Netz darunter.
     */
    Country::factory()->create(['code' => 'de', 'name' => 'Germany']);

    $response = $this->getJson('/api/countries');

    $response->assertSuccessful();

    expect($response->json())->toBeArray()
        ->and($response->json())->not->toHaveKey('data')
        ->and($response->json(0))->toHaveKeys(['id', 'name', 'code', 'flag']);
});

it('keeps the four established fields byte for byte', function () {
    $country = Country::factory()->create(['code' => 'de', 'name' => 'Germany']);

    $row = $this->getJson('/api/countries?search=Germany')->json(0);

    expect($row['id'])->toBe($country->id)
        ->and($row['name'])->toBe('Germany')
        ->and($row['code'])->toBe('de')
        ->and($row['flag'])->toBe(asset('vendor/blade-flags/country-de.svg'));
});

it('adds the osm reference without demanding it', function () {
    Country::factory()->create(['code' => 'de', 'name' => 'Germany']);

    $row = $this->getJson('/api/countries?search=Germany')->json(0);

    // Ohne Referenz sind die neuen Felder null — der Normalfall fuer Bestandsdaten.
    expect($row)->toHaveKeys(['osm_type', 'osm_id', 'osm_url', 'wikidata', 'wikipedia'])
        ->and($row['osm_url'])->toBeNull()
        ->and($row['wikipedia_url'])->toBeNull();
});

it('builds the derived urls once a reference is set', function () {
    Country::factory()->create([
        'code' => 'de',
        'name' => 'Germany',
        'osm_type' => 'relation',
        'osm_id' => 51477,
        'wikidata' => 'Q183',
        'wikipedia' => 'de:Deutschland',
    ]);

    $row = $this->getJson('/api/countries?search=Germany')->json(0);

    expect($row['osm_url'])->toBe('https://www.openstreetmap.org/relation/51477')
        ->and($row['wikidata_url'])->toBe('https://www.wikidata.org/wiki/Q183')
        ->and($row['wikipedia_url'])->toBe('https://de.wikipedia.org/wiki/Deutschland');
});

it('returns null for a wikipedia value without a language prefix', function () {
    // "Berlin" ohne Praefix ergibt keine Domain — dann lieber null als ein toter Link.
    Country::factory()->create(['code' => 'de', 'name' => 'Germany', 'wikipedia' => 'Berlin']);

    expect($this->getJson('/api/countries?search=Germany')->json(0)['wikipedia_url'])->toBeNull();
});
