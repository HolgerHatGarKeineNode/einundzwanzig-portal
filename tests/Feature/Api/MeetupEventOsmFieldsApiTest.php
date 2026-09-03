<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;

/*
 * Issue #37 follow-up: the six osm_* fields on the PUBLIC list endpoint.
 *
 * MeetupEventResource (the authenticated endpoints, the change-log envelope) has
 * carried them since #37. GET /api/meetup-events hand-builds its own array and
 * carried only the free-text `location` — so the consumer who asked for the map
 * reference could read it back through every endpoint except the one that lists
 * events publicly.
 *
 * The keys are asserted as PRESENT-AND-NULL, not as absent, for an event without a
 * map place. A key that appears only sometimes forces a typed client to model the
 * field as optional AND nullable, and makes "no map place" indistinguishable from
 * "this endpoint does not serve map places".
 */

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    $this->meetup = Meetup::factory()->create(['city_id' => $city->id]);
});

it('exposes all six osm fields for an event that has a map place', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'location' => 'Bürgerhaus, Seiteneingang',
        'osm_type' => 'way',
        'osm_id' => 123456789,
        'osm_name' => 'Bürgerhaus',
        'osm_address' => 'Hauptstraße 1, 90402 Nürnberg',
        'osm_lat' => 49.4521000,
        'osm_lon' => 11.0767000,
    ]);

    $row = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    // The free text stays alongside the map reference — they are not alternatives.
    expect($row['location'])->toBe('Bürgerhaus, Seiteneingang')
        ->and($row['osm_type'])->toBe('way')
        ->and($row['osm_id'])->toBe(123456789)
        ->and($row['osm_name'])->toBe('Bürgerhaus')
        ->and($row['osm_address'])->toBe('Hauptstraße 1, 90402 Nürnberg')
        // Strings, not floats: the decimal:7 cast keeps the precision that a JSON
        // float would round away, and MeetupEventResource serialises them the same.
        ->and($row['osm_lat'])->toBe('49.4521000')
        ->and($row['osm_lon'])->toBe('11.0767000');
});

it('keeps all six osm keys present and null for an event without a map place', function () {
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'location' => 'Watch the Signal group',
        'osm_type' => null,
        'osm_id' => null,
        'osm_name' => null,
        'osm_address' => null,
        'osm_lat' => null,
        'osm_lon' => null,
    ]);

    $row = collect($this->getJson('/api/meetup-events')->assertOk()->json())
        ->firstWhere('id', $event->id);

    foreach (['osm_type', 'osm_id', 'osm_name', 'osm_address', 'osm_lat', 'osm_lon'] as $field) {
        expect($row)->toHaveKey($field)
            ->and($row[$field])->toBeNull();
    }

    expect($row['location'])->toBe('Watch the Signal group');
});
