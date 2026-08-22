<?php

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Germany']);
});

it('stores the osm reference chosen in the form', function () {
    Livewire::test('cities.create')
        ->set('name', 'Berlin OSM Test')
        ->set('country_id', $this->country->id)
        ->set('latitude', 52.52)
        ->set('longitude', 13.405)
        ->set('osmPlace', [
            'osm_type' => 'relation',
            'osm_id' => 62422,
            'osm_name' => 'Berlin',
            'osm_address' => 'Berlin, Deutschland',
            'osm_lat' => 52.5173885,
            'osm_lon' => 13.3951309,
            'wikidata' => 'Q64',
            'wikipedia' => 'de:Berlin',
        ])
        ->call('createCity')
        ->assertHasNoErrors();

    $city = City::firstWhere('name', 'Berlin OSM Test');

    expect($city->osm_type)->toBe('relation')
        ->and($city->osm_id)->toBe(62422)
        ->and($city->wikidata)->toBe('Q64')
        ->and($city->osm_url)->toBe('https://www.openstreetmap.org/relation/62422')
        ->and($city->wikipedia_url)->toBe('https://de.wikipedia.org/wiki/Berlin');
});

it('creates a city without any osm reference exactly as before', function () {
    Livewire::test('cities.create')
        ->set('name', 'Ohne OSM')
        ->set('country_id', $this->country->id)
        ->set('latitude', 51.0)
        ->set('longitude', 9.0)
        ->call('createCity')
        ->assertHasNoErrors();

    $city = City::firstWhere('name', 'Ohne OSM');

    expect($city)->not->toBeNull()
        ->and($city->osm_id)->toBeNull()
        ->and($city->osm_url)->toBeNull();
});

it('fills empty coordinates from the chosen place but never overwrites entered ones', function () {
    // Eine von Hand eingetragene Korrektur zu ueberschreiben waere die unangenehmste
    // Art, hilfsbereit zu sein.
    Livewire::test('cities.create')
        ->set('country_id', $this->country->id)
        ->set('latitude', 1.234)
        ->set('osmPlace', ['osm_id' => 62422, 'osm_lat' => 52.5, 'osm_lon' => 13.4, 'population' => 3769962])
        ->assertSet('latitude', 1.234)
        ->assertSet('longitude', 13.4)
        ->assertSet('population', 3769962);
});

it('keeps the reference when the city is edited without touching it', function () {
    $city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'Bestandsstadt',
        'osm_type' => 'relation',
        'osm_id' => 62422,
        'wikidata' => 'Q64',
    ]);

    Livewire::test('cities.edit', ['city' => $city])
        ->set('population', 12345)
        ->call('updateCity')
        ->assertHasNoErrors();

    expect($city->fresh()->osm_id)->toBe(62422)
        ->and($city->fresh()->wikidata)->toBe('Q64');
});

it('accepts a city from the api with an osm place and no own coordinates', function () {
    // Wunsch aus Issue #11: Name + Land + OSM-Ort sollen genuegen. Die Koordinaten des
    // Orts wandern in latitude/longitude, denn die Spalten sind NOT NULL.
    $response = $this->postJson('/api/cities', [
        'country_id' => $this->country->id,
        'name' => 'Nur mit OSM',
        'osm_type' => 'relation',
        'osm_id' => 62422,
        'osm_lat' => 52.5173885,
        'osm_lon' => 13.3951309,
        'wikidata' => 'Q64',
        'wikipedia' => 'de:Berlin',
    ]);

    $response->assertSuccessful();

    expect($response->json('data.osm_url'))->toBe('https://www.openstreetmap.org/relation/62422')
        ->and($response->json('data.wikipedia_url'))->toBe('https://de.wikipedia.org/wiki/Berlin')
        ->and((float) $response->json('data.latitude'))->toBe(52.5173885);
});

it('refuses an osm place that brings no coordinates either', function () {
    // Ohne osm_lat/osm_lon waere die Stadt ein Punkt ohne Ort — die Karte braucht beides.
    $this->postJson('/api/cities', [
        'country_id' => $this->country->id,
        'name' => 'OSM ohne Koordinaten',
        'osm_type' => 'relation',
        'osm_id' => 62422,
    ])->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('still rejects a city without coordinates and without an osm place', function () {
    $this->postJson('/api/cities', [
        'country_id' => $this->country->id,
        'name' => 'Weder noch',
    ])->assertJsonValidationErrors(['latitude', 'longitude']);
});

it('rejects half an osm pair', function () {
    $this->postJson('/api/cities', [
        'country_id' => $this->country->id,
        'name' => 'Halbes Paar',
        'osm_id' => 62422,
    ])->assertJsonValidationErrors(['osm_type']);
});
