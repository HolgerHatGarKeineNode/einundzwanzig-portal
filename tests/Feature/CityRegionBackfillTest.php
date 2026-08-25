<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Region;

/*
 * P4 (docs/plans/2026-08-25T1848-formular-und-policy-lueckenschluss.md):
 * `cities:backfill-regions` fuellt `cities.region_id` aus der bereits gespeicherten
 * OSM-Adresse — und zwar NUR, wo genau eine Region darin steht. Die drei Faelle, die
 * dieses Kommando auseinanderhalten muss, sind hier je einzeln festgenagelt:
 * eindeutig (zuordnen), mehrdeutig (leer lassen und zaehlen), ohne Daten (leer lassen).
 *
 * Warum das Leerlassen mitgetestet wird und nicht nur das Zuordnen: `region_id` ist ein
 * Identitaetsfeld hinter CityPolicy::updateIdentity(). Eine falsche Zuordnung kann ein
 * Nutzer ohne Steward-Recht nicht mehr korrigieren — ein geratener Wert waere also
 * teurer als ein fehlender.
 */

beforeEach(function () {
    $this->de = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);

    $this->bayern = Region::factory()->create([
        'country_id' => $this->de->id,
        'code' => 'by',
        'name' => 'Bayern',
    ]);

    $this->niedersachsen = Region::factory()->create([
        'country_id' => $this->de->id,
        'code' => 'ni',
        'name' => 'Niedersachsen',
    ]);

    $this->bremen = Region::factory()->create([
        'country_id' => $this->de->id,
        'code' => 'hb',
        'name' => 'Bremen',
    ]);
});

it('assigns the region when exactly one stands in the OSM address', function () {
    $city = City::factory()->create([
        'country_id' => $this->de->id,
        'region_id' => null,
        'name' => 'München',
        'osm_address' => 'München, Bayern, Deutschland',
    ]);

    $this->artisan('cities:backfill-regions')
        ->expectsOutputToContain('zugeordnet 1')
        ->assertSuccessful();

    expect($city->refresh()->region_id)->toBe($this->bayern->id);
});

it('assigns on the ISO code as well, not only on the name', function () {
    $usa = Country::factory()->create(['code' => 'us']);
    $indiana = Region::factory()->indiana()->create(['country_id' => $usa->id]);

    $city = City::factory()->create([
        'country_id' => $usa->id,
        'region_id' => null,
        'name' => 'Indianapolis',
        'osm_address' => 'Indianapolis, Marion County, IN, United States',
    ]);

    $this->artisan('cities:backfill-regions', ['--country' => ['us']])->assertSuccessful();

    expect($city->refresh()->region_id)->toBe($indiana->id);
});

it('leaves a city empty and counts it when the address names two regions', function () {
    // Nominatim liefert fuer Orte an der Landesgrenze durchaus beide Ebenen in einer
    // Zeile. Welche davon gemeint ist, entscheidet dieses Kommando nicht.
    $city = City::factory()->create([
        'country_id' => $this->de->id,
        'region_id' => null,
        'name' => 'Grenzort',
        'osm_address' => 'Grenzort, Bremen, Niedersachsen, Deutschland',
    ]);

    $this->artisan('cities:backfill-regions')
        ->expectsOutputToContain('mehrdeutig 1')
        ->assertSuccessful();

    expect($city->refresh()->region_id)->toBeNull();
});

it('leaves a city empty when a search term matches two regions of the same country', function () {
    // Der Name der einen Region ist der Code der anderen: der Begriff selbst ist
    // mehrdeutig, unabhaengig davon, wie oft er in der Adresse steht.
    Region::factory()->create(['country_id' => $this->de->id, 'code' => 'zz', 'name' => 'By']);

    $city = City::factory()->create([
        'country_id' => $this->de->id,
        'region_id' => null,
        'name' => 'Doppeldeutig',
        'osm_address' => 'Doppeldeutig, By, Deutschland',
    ]);

    $this->artisan('cities:backfill-regions')
        ->expectsOutputToContain('mehrdeutig 1')
        ->assertSuccessful();

    expect($city->refresh()->region_id)->toBeNull();
});

it('leaves a city without OSM data empty and counts it separately', function () {
    $city = City::factory()->create([
        'country_id' => $this->de->id,
        'region_id' => null,
        'name' => 'Ohne Daten',
        'osm_address' => null,
    ]);

    $this->artisan('cities:backfill-regions')
        ->expectsOutputToContain('ohne OSM-Adresse 1')
        ->assertSuccessful();

    expect($city->refresh()->region_id)->toBeNull();
});

it('does not match on a substring of a longer place name', function () {
    // "Bremen" steckt in "Bremerhaven" nicht, aber "Bremen" in "Bremen-Nord" schon —
    // verglichen wird deshalb das ganze Adressglied, nicht ein Teilstring.
    $city = City::factory()->create([
        'country_id' => $this->de->id,
        'region_id' => null,
        'name' => 'Bremen-Nord',
        'osm_address' => 'Bremen-Nord, Deutschland',
    ]);

    $this->artisan('cities:backfill-regions')
        ->expectsOutputToContain('ohne Treffer in der Adresse 1')
        ->assertSuccessful();

    expect($city->refresh()->region_id)->toBeNull();
});

it('writes nothing at all with --dry-run', function () {
    $city = City::factory()->create([
        'country_id' => $this->de->id,
        'region_id' => null,
        'name' => 'München',
        'osm_address' => 'München, Bayern, Deutschland',
    ]);

    $this->artisan('cities:backfill-regions', ['--dry-run' => true])
        ->expectsOutputToContain('--dry-run: nichts geschrieben.')
        ->expectsOutputToContain('zugeordnet 1')
        ->assertSuccessful();

    expect($city->refresh()->region_id)->toBeNull();

    // Und der Trockenlauf hat den echten Lauf nicht praejudiziert.
    $this->artisan('cities:backfill-regions')->assertSuccessful();

    expect($city->refresh()->region_id)->toBe($this->bayern->id);
});

it('never touches a city that already carries a region', function () {
    $city = City::factory()->create([
        'country_id' => $this->de->id,
        'region_id' => $this->niedersachsen->id,
        'name' => 'Falsch zugeordnet',
        // Die Adresse sagt Bayern, der Bestand sagt Niedersachsen. Eine bestehende
        // Zuordnung ist eine Entscheidung — kein Automat korrigiert sie.
        'osm_address' => 'Falsch zugeordnet, Bayern, Deutschland',
    ]);

    $this->artisan('cities:backfill-regions')->assertSuccessful();

    expect($city->refresh()->region_id)->toBe($this->niedersachsen->id);
});

it('restricts itself to the countries given on --country', function () {
    $usa = Country::factory()->create(['code' => 'us']);
    Region::factory()->indiana()->create(['country_id' => $usa->id]);

    $deutsch = City::factory()->create([
        'country_id' => $this->de->id,
        'region_id' => null,
        'name' => 'München',
        'osm_address' => 'München, Bayern, Deutschland',
    ]);

    $amerikanisch = City::factory()->create([
        'country_id' => $usa->id,
        'region_id' => null,
        'name' => 'Indianapolis',
        'osm_address' => 'Indianapolis, Indiana, United States',
    ]);

    $this->artisan('cities:backfill-regions', ['--country' => ['us']])->assertSuccessful();

    expect($amerikanisch->refresh()->region_id)->not->toBeNull()
        ->and($deutsch->refresh()->region_id)->toBeNull();
});

it('fills a region route that was empty before (DoD P4)', function () {
    $city = City::factory()->create([
        'country_id' => $this->de->id,
        'region_id' => null,
        'name' => 'Regensburg',
        'osm_address' => 'Regensburg, Bayern, Deutschland',
    ]);

    // Vorher: die Regionsroute existiert, ist aber leer — genau der Zustand, den P4
    // beheben soll (Regionszeilen, die niemand sieht).
    $this->get('/de/by/cities')->assertOk()->assertDontSee('Regensburg');

    $this->artisan('cities:backfill-regions')->assertSuccessful();

    $this->get('/de/by/cities')->assertOk()->assertSee('Regensburg');

    expect($city->refresh()->region_id)->toBe($this->bayern->id);
});
