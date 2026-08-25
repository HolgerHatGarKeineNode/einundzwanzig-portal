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
| N11 (confirm_duplicate auf UpdateCityTool) fehlt absichtlich — siehe den
| Kommentar am Ende dieser Datei, derselbe Fund wie in
| CityIdentityResolutionApiTest.php, nur ueber den MCP-Pfad reproduziert.
|--------------------------------------------------------------------------
*/

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\City\CreateCityTool;
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
| Fund, nicht Test: confirm_duplicate auf UpdateCityTool crasht
|--------------------------------------------------------------------------
|
| UpdateCityTool::handle() validiert per UpdateCityRequest::forCity(...)->rules()
| (die confirm_duplicate als sometimes|boolean zulaesst) und ruft danach
| $city->update($validated) OHNE Arr::except(..., [City::CONFIRM_DUPLICATE]) —
| genau der Schritt, den City::resolveOrCreate() vor jedem create() macht
| (City.php:236/250/278). 'confirm_duplicate' ist keine Spalte auf `cities`.
|
| Belegt per vendor/bin/pest --agent (2026-08-25): ein PATCH-aequivalenter
| update-city-Aufruf mit confirm_duplicate=true auf eine Umbenennung, die im
| selben Land kollidiert, liefert KEINE Antwort mit dem neuen Namen — die
| Fehlerliste der Response enthaelt woertlich:
|
|   "SQLSTATE[HY000]: General error: 1 no such column: confirm_duplicate
|   (... update "cities" set "name" = Regensburg, "confirm_duplicate" = 1 ...)"
|
| Derselbe Fund, derselbe Fix-Kandidat wie beim REST-Pfad (siehe
| CityIdentityResolutionApiTest.php): Arr::except($validated, [City::CONFIRM_DUPLICATE])
| vor dem update() in BEIDEN Controllern/Tools. Kein Test dafuer hier, weil er
| dauerhaft rot waere — Produktivcode wird von dieser Testreihe nicht
| angefasst.
|--------------------------------------------------------------------------
*/
