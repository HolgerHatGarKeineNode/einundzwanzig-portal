<?php

/*
|--------------------------------------------------------------------------
| Issue #33 — REST-Verdrahtung von City::resolveOrCreate()/UpdateCityRequest
|--------------------------------------------------------------------------
|
| Die Logik selbst ist in tests/Feature/Cities/CityResolveOrCreateTest.php
| geprueft (Model-Ebene, niedrigste Ebene, die den Fehler faengt). Hier nur
| noch die Verdrahtung: kommt die richtige HTTP-Antwort raus, wenn dieselben
| Faelle durch den Controller laufen? N4 (Kandidatenliste im JSON), N5
| (confirm_duplicate ueber die API), N6 (OSM-Referenz macht mehrdeutige Namen
| eindeutig), N11 (PATCH-Rename-Matrix: selbes Land blockiert, anderes Land
| erlaubt, confirm_duplicate hebt es auf).
|--------------------------------------------------------------------------
*/

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// N4 — mehrere Treffer: 422, und die Antwort enthaelt jede id.
it('lists every candidate id in the 422 response when the name is ambiguous', function () {
    Sanctum::actingAs(User::factory()->create());
    $country = Country::factory()->create();
    $cities = neuenkirchenCities($country);

    $response = $this->postJson('/api/cities', [
        'name' => 'Neuenkirchen',
        'country_id' => $country->id,
        'latitude' => 52.5, 'longitude' => 8.0,
    ]);

    $response->assertStatus(422);
    $message = $response->json('errors.name.0');

    foreach ($cities as $city) {
        expect($message)->toContain('#'.$city->id);
    }
});

// N5 — confirm_duplicate ueber die API legt trotz bestehendem Georgetown ein zweites an.
it('creates a second same-named city in the same country via confirm_duplicate', function () {
    Sanctum::actingAs(User::factory()->create());
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Georgetown', 'country_id' => $country->id]);

    $response = $this->postJson('/api/cities', [
        'name' => 'Georgetown',
        'country_id' => $country->id,
        'latitude' => 38.32, 'longitude' => -85.87,
        'confirm_duplicate' => true,
    ]);

    $response->assertCreated();

    expect(City::query()->where('name', 'Georgetown')->where('country_id', $country->id)->count())->toBe(2);
});

// N6 — eine mitgeschickte OSM-Referenz macht einen mehrdeutigen Namen eindeutig anlegbar.
it('creates via OSM reference over the API even when the name is ambiguous in that country', function () {
    Sanctum::actingAs(User::factory()->create());
    $country = Country::factory()->create();
    neuenkirchenCities($country);

    $response = $this->postJson('/api/cities', [
        'name' => 'Neuenkirchen',
        'country_id' => $country->id,
        'latitude' => 52.9, 'longitude' => 8.9,
        'osm_type' => 'relation',
        'osm_id' => 900654321,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.osm_id', 900654321);

    expect(City::query()->where('name', 'Neuenkirchen')->count())->toBe(9);
});

/*
|--------------------------------------------------------------------------
| N11 — confirm_duplicate auf PATCH: NICHT abgedeckt, gemeldet statt getestet.
|--------------------------------------------------------------------------
|
| CityController::update() reicht $request->validated() unveraendert an
| $city->update() durch — anders als City::resolveOrCreate(), das
| Arr::except($attributes, [City::CONFIRM_DUPLICATE]) VOR create() aufruft
| (City.php:236/250/278). 'confirm_duplicate' ist aber keine Spalte auf
| `cities`. Ergebnis: jedes PATCH mit confirm_duplicate=true wirft eine
| QueryException ("no such column: confirm_duplicate"), die als 500
| rausgeht — der Rename-Workaround, den N11 hier eigentlich pruefen sollte,
| crasht. Derselbe Fehler steckt in UpdateCityTool::handle() (MCP), siehe
| tests/Feature/Mcp/CityIdentityResolutionMcpTest.php. Kein Test dafuer HIER,
| weil er dauerhaft rot waere und Produktivcode nicht angefasst wird (Auftrag)
| — belegt per vendor/bin/pest --agent, gemeldet im Abschlussbericht.
|--------------------------------------------------------------------------
*/
