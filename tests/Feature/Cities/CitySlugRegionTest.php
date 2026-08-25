<?php

/*
|--------------------------------------------------------------------------
| staedte-identitaet P5 — City::getSlugOptions() traegt jetzt die Region
|--------------------------------------------------------------------------
|
| P5-c (ein bestehender Slug aendert sich bei einem Update nicht) ist bereits
| in tests/Feature/SlugStabilityTest.php abgedeckt
| ("keeps the city slug when the city is updated") — dieselbe Zusage, kein
| zweiter Test dafuer hier.
|--------------------------------------------------------------------------
*/

use App\Models\City;
use App\Models\Country;
use App\Models\Region;

// P5-a — mit Region steckt ihr Code im Slug: zwei Springfields werden unterscheidbar.
it('puts the region code in the slug when the city has one', function () {
    $us = Country::factory()->create(['code' => 'us']);
    $il = Region::factory()->create(['country_id' => $us->id, 'code' => 'il']);
    $mo = Region::factory()->create(['country_id' => $us->id, 'code' => 'mo']);

    $springfieldIl = City::factory()->create(['name' => 'Springfield', 'country_id' => $us->id, 'region_id' => $il->id, 'slug' => null]);
    $springfieldMo = City::factory()->create(['name' => 'Springfield', 'country_id' => $us->id, 'region_id' => $mo->id, 'slug' => null]);

    expect($springfieldIl->slug)->toBe('us-il-springfield')
        ->and($springfieldMo->slug)->toBe('us-mo-springfield')
        ->and($springfieldIl->slug)->not->toBe($springfieldMo->slug);
});

// P5-b — DIE Bedingung, unter der P5 ueberhaupt vertretbar ist: ohne Region entsteht
// exakt derselbe Slug wie vor der Aenderung (country.code + name, keine Zaehlnummer).
it('produces the exact same slug as before when the city has no region', function () {
    $de = Country::factory()->create(['code' => 'de']);

    $city = City::factory()->create(['name' => 'Köln', 'country_id' => $de->id, 'region_id' => null, 'slug' => null]);

    expect($city->slug)->toBe('de-koeln');
});

// P5-b, mehrfach — dieselbe Zusage ueber mehrere region-freie Staedte hinweg, nicht nur
// einen Einzelfall (300 von 305 Produktionsstaedten haben keine Region).
it('leaves every region-free city slug unaffected by the region change', function () {
    $de = Country::factory()->create(['code' => 'de']);

    $cities = collect(['Hamburg', 'München', 'Leipzig', 'Frankfurt'])
        ->map(fn (string $name) => City::factory()->create(['name' => $name, 'country_id' => $de->id, 'region_id' => null, 'slug' => null]));

    expect($cities->pluck('slug')->all())->toBe([
        'de-hamburg', 'de-muenchen', 'de-leipzig', 'de-frankfurt',
    ]);
});

// P5-d — zwei gleichnamige Orte in DERSELBEN Region bekommen weiterhin eine
// Zaehlnummer. Dokumentiert als Verhalten, kein Fehler (mehr Unterscheidung gibt die
// Verwaltungsebene nicht her).
it('still appends a counter for two same-named cities in the same region', function () {
    $us = Country::factory()->create(['code' => 'us']);
    $il = Region::factory()->create(['country_id' => $us->id, 'code' => 'il']);

    $first = City::factory()->create(['name' => 'Springfield', 'country_id' => $us->id, 'region_id' => $il->id, 'slug' => null]);
    $second = City::factory()->create(['name' => 'Springfield', 'country_id' => $us->id, 'region_id' => $il->id, 'slug' => null]);

    expect($first->slug)->toBe('us-il-springfield')
        ->and($second->slug)->toBe('us-il-springfield-1');
});
