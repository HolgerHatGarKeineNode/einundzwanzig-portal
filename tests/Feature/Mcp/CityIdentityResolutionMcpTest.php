<?php

/*
|--------------------------------------------------------------------------
| Issue #33 — MCP-Verdrahtung von City::resolveOrCreate()
|--------------------------------------------------------------------------
|
| Logik selbst: tests/Feature/Cities/CityResolveOrCreateTest.php. Hier nur die
| Verdrahtung von CreateCityTool — kommt bei denselben Faellen die richtige
| MCP-Antwort raus? N4 (Kandidatenliste), N5 (confirm_duplicate), N6
| (OSM-Referenz macht mehrdeutige Namen eindeutig).
|
| N11 (confirm_duplicate auf UpdateCityTool) ist seit 2026-08-25 gefixt — siehe
| den Test am Ende dieser Datei, derselbe Fix wie in
| CityIdentityResolutionApiTest.php, nur ueber den MCP-Pfad geprueft.
|--------------------------------------------------------------------------
*/

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\City\CreateCityTool;
use App\Mcp\Tools\City\UpdateCityTool;
use App\Models\City;
use App\Models\Country;
use App\Models\User;

// N4 — mehrere Treffer: Fehlerantwort, und sie enthaelt jede id.
it('lists every candidate id when the name is ambiguous', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();
    $cities = neuenkirchenCities($country);

    $response = EinundzwanzigServer::actingAs($user)
        ->tool(CreateCityTool::class, [
            'name' => 'Neuenkirchen',
            'country_id' => $country->id,
            'latitude' => 52.5, 'longitude' => 8.0,
        ]);

    $response->assertHasErrors();

    foreach ($cities as $city) {
        $response->assertSee('#'.$city->id);
    }
});

// N5 — confirm_duplicate legt trotz bestehendem Georgetown ein zweites an.
it('creates a second same-named city in the same country via confirm_duplicate', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Georgetown', 'country_id' => $country->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateCityTool::class, [
            'name' => 'Georgetown',
            'country_id' => $country->id,
            'latitude' => 38.32, 'longitude' => -85.87,
            'confirm_duplicate' => true,
        ])
        ->assertOk();

    expect(City::query()->where('name', 'Georgetown')->where('country_id', $country->id)->count())->toBe(2);
});

// N6 — eine mitgeschickte OSM-Referenz macht einen mehrdeutigen Namen eindeutig anlegbar.
it('creates via OSM reference even when the name is ambiguous in that country', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();
    neuenkirchenCities($country);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateCityTool::class, [
            'name' => 'Neuenkirchen',
            'country_id' => $country->id,
            'latitude' => 52.9, 'longitude' => 8.9,
            'osm_type' => 'relation',
            'osm_id' => 900654321,
        ])
        ->assertOk();

    expect(City::query()->where('name', 'Neuenkirchen')->count())->toBe(9);
    expect(City::query()->where('osm_id', 900654321)->exists())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| N11 — confirm_duplicate auf UpdateCityTool, jetzt gefixt.
|--------------------------------------------------------------------------
|
| Bis hierher validierte UpdateCityTool::handle() per
| UpdateCityRequest::forCity(...)->rules() (die confirm_duplicate als
| sometimes|boolean zulaesst) und rief danach $city->update($validated) OHNE
| Arr::except(..., [City::CONFIRM_DUPLICATE]) — 'confirm_duplicate' ist keine
| Spalte auf `cities`, jedes update() mit gesetztem Flag warf eine
| QueryException. Der Fix steht jetzt in UpdateCityTool::handle():
| `$city->update(Arr::except($validated, [City::CONFIRM_DUPLICATE]));`
| — derselbe Fix im REST-Pfad, siehe CityIdentityResolutionApiTest.php.
|--------------------------------------------------------------------------
*/

// N11 — confirm_duplicate ueber update-city fuehrt zu Erfolg, nicht zu einer
// QueryException durch eine unbekannte Spalte.
it('renames via update-city with confirm_duplicate instead of crashing', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Regensburg', 'country_id' => $country->id]);
    $mine = City::factory()->create(['name' => 'Ansbach', 'country_id' => $country->id, 'created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateCityTool::class, [
            'id' => $mine->id,
            'name' => 'Regensburg',
            'confirm_duplicate' => true,
        ])
        ->assertOk();

    expect($mine->fresh()->name)->toBe('Regensburg')
        ->and(City::query()->where('name', 'Regensburg')->where('country_id', $country->id)->count())->toBe(2);
});

// Gegenprobe: ohne confirm_duplicate blockiert derselbe Rename weiterhin mit einer
// Fehlerantwort, nicht mit einer QueryException.
it('still blocks the same update-city rename when confirm_duplicate is missing', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Regensburg', 'country_id' => $country->id]);
    $mine = City::factory()->create(['name' => 'Ansbach', 'country_id' => $country->id, 'created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateCityTool::class, [
            'id' => $mine->id,
            'name' => 'Regensburg',
        ])
        ->assertHasErrors();

    expect($mine->fresh()->name)->toBe('Ansbach');
});
