<?php

/*
|--------------------------------------------------------------------------
| Issue #30 — CityPolicy in zwei Abilities: update() (Anreichern, offen fuer
| jeden angemeldeten Nutzer) und updateIdentity() (Name, Land, Region,
| Einwohnerzahl, Stichjahr — nur Ersteller, city-steward oder Super-Admin).
|--------------------------------------------------------------------------
|
| Diese Datei sichert den neuen Vertrag ueber alle drei Schreibpfade ab, wo er
| nicht bereits durch CityWriteApiTest / CityMcpToolTest gedeckt ist:
|
|  N1 — Anreichern ist offen (REST + Portal).
|  N2 — Identitaet ist geschuetzt (REST + Portal; MCP siehe CityMcpToolTest
|       "forbids updating someone elses city").
|  N3 — Unveraendertes ist keine Aenderung, inklusive loser Typ-Vergleich.
|  N4 — Die city-steward-Rolle wirkt (REST).
|  N6 — Fail-closed fuer Gaeste.
*/

use App\Models\City;
use App\Models\Country;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->creator = User::factory()->create();
    $this->city = City::factory()->create([
        'country_id' => $this->country->id,
        'created_by' => $this->creator->id,
        'name' => 'Foreign City',
        'population' => 100,
    ]);
});

/*
|--------------------------------------------------------------------------
| N1 — Anreichern ist offen
|--------------------------------------------------------------------------
*/

it('lets a non-creator enrich a foreign city with an OSM reference via the api', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->patchJson("/api/cities/{$this->city->id}", [
        'osm_type' => 'node',
        'osm_id' => 240109189,
        'osm_name' => 'Foreign City OSM',
        'osm_address' => 'Somewhere 1',
        'osm_lat' => 51.6739,
        'osm_lon' => 8.3444,
        'wikidata' => 'Q64',
        'wikipedia' => 'de:Foreign City',
    ]);

    $response->assertSuccessful();

    $this->assertDatabaseHas('cities', [
        'id' => $this->city->id,
        'osm_id' => 240109189,
        'wikidata' => 'Q64',
    ]);
});

it('lets a non-creator enrich a foreign city via the portal without touching identity', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('cities.edit', ['city' => $this->city])
        ->set('osmPlace', [
            'osm_type' => 'node',
            'osm_id' => 240109189,
            'osm_name' => 'Foreign City OSM',
            'osm_address' => 'Somewhere 1',
            'osm_lat' => 51.6739,
            'osm_lon' => 8.3444,
            'wikidata' => 'Q64',
            'wikipedia' => 'de:Foreign City',
        ])
        ->call('updateCity')
        ->assertHasNoErrors();

    expect($this->city->fresh())
        ->osm_id->toBe(240109189)
        ->wikidata->toBe('Q64')
        // Der Nachweis, dass wirklich nichts an der Identitaet geruehrt wurde.
        ->name->toBe('Foreign City');
});

/*
|--------------------------------------------------------------------------
| N2 — Identitaet ist geschuetzt (REST + Portal)
|--------------------------------------------------------------------------
*/

it('forbids a non-creator from renaming a foreign city via the api', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->patchJson("/api/cities/{$this->city->id}", [
        'name' => 'Hijacked',
    ]);

    $response->assertForbidden();

    expect($this->city->fresh()->name)->toBe('Foreign City');
});

it('forbids a non-creator from changing population_date on a foreign city via the api', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->patchJson("/api/cities/{$this->city->id}", [
        'population_date' => '2024',
    ]);

    $response->assertForbidden();

    expect($this->city->fresh()->population_date)->not->toBe('2024');
});

it('forbids a non-creator from renaming a foreign city via the portal', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('cities.edit', ['city' => $this->city])
        ->set('name', 'Hijacked')
        ->call('updateCity')
        ->assertForbidden();

    expect($this->city->fresh()->name)->toBe('Foreign City');
});

/*
|--------------------------------------------------------------------------
| N3 — Unveraendertes ist keine Aenderung
|--------------------------------------------------------------------------
*/

it('lets a non-creator submit unchanged identity fields while only enriching via the api', function () {
    Sanctum::actingAs(User::factory()->create());

    // Ein Formular schickt immer alles mit — auch name und country_id, obwohl sie
    // sich nicht aendern. Das darf kein 403 ausloesen.
    $response = $this->patchJson("/api/cities/{$this->city->id}", [
        'name' => $this->city->name,
        'country_id' => $this->city->country_id,
        'osm_type' => 'node',
        'osm_id' => 240109189,
    ]);

    $response->assertSuccessful();

    expect($this->city->fresh()->osm_id)->toBe(240109189);
});

it('treats a stringified population equal to the stored value as unchanged', function () {
    Sanctum::actingAs(User::factory()->create());

    // Gespeichert ist population = 100 (int). Ein Formular schickt "100" als String —
    // das ist derselbe Wert und darf keine Identitaetsaenderung ausloesen.
    $response = $this->patchJson("/api/cities/{$this->city->id}", [
        'population' => '100',
        'osm_type' => 'node',
        'osm_id' => 240109189,
    ]);

    $response->assertSuccessful();

    expect($this->city->fresh())->population->toBe(100)->osm_id->toBe(240109189);
});

/*
|--------------------------------------------------------------------------
| N4 — Die city-steward-Rolle wirkt
|--------------------------------------------------------------------------
*/

it('lets a city steward rename a foreign city via the api', function () {
    $steward = User::factory()->create();
    $steward->assignRole(Role::findOrCreate(User::ROLE_CITY_STEWARD, 'web'));

    Sanctum::actingAs($steward);

    $response = $this->patchJson("/api/cities/{$this->city->id}", [
        'name' => 'Renamed By Steward',
    ]);

    $response->assertSuccessful();

    expect($this->city->fresh()->name)->toBe('Renamed By Steward');
});

it('still forbids the same rename without the steward role', function () {
    $notASteward = User::factory()->create();

    Sanctum::actingAs($notASteward);

    $response = $this->patchJson("/api/cities/{$this->city->id}", [
        'name' => 'Renamed Without Role',
    ]);

    $response->assertForbidden();

    expect($this->city->fresh()->name)->toBe('Foreign City');
});

/*
|--------------------------------------------------------------------------
| N6 — Fail-closed fuer Gaeste
|--------------------------------------------------------------------------
*/

it('redirects a guest to login before the edit component ever mounts', function () {
    // Die 'auth'-Middleware der Routen-Gruppe faengt einen Gast schon vor dem
    // Livewire-Mount ab — $canEditIdentity wird fuer einen Gast nie berechnet.
    $this->get(route('cities.edit', ['country' => 'de', 'city' => $this->city]))
        ->assertRedirect(route('login'));
});

it('refuses to mount the edit component directly for a guest', function () {
    // Unabhaengig von der Routen-Middleware: mount() ruft selbst authorize('update', ...)
    // auf, BEVOR canEditIdentity gesetzt wird. Livewire::test() umgeht die HTTP-Middleware,
    // darum ist das die Probe auf die eigene Verteidigung der Komponente.
    Livewire::test('cities.edit', ['city' => $this->city])
        ->assertForbidden();
});
