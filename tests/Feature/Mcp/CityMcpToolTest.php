<?php

use App\Mcp\Servers\EinundzwanzigServer;
use App\Mcp\Tools\City\CreateCityTool;
use App\Mcp\Tools\City\ListMyCitiesTool;
use App\Mcp\Tools\City\ShowMyCityTool;
use App\Mcp\Tools\City\UpdateCityTool;
use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;

it('lets an authenticated user create a city and stamps created_by', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();

    $response = EinundzwanzigServer::actingAs($user)->tool(CreateCityTool::class, [
        'name' => 'Ansbach',
        'country_id' => $country->id,
        'longitude' => 10.5806,
        'latitude' => 49.3034,
    ]);

    $response->assertOk()->assertSee('Ansbach');

    $this->assertDatabaseHas('cities', [
        'name' => 'Ansbach',
        'created_by' => $user->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Issue #33 — City::resolveOrCreate() loest ueber Name + Land auf, nicht mehr
| ueber den Namen allein. Diese beiden Tests waren bis 2026-08-25 EIN Test, der
| das alte (falsche) Verhalten festnagelte: eine Stadt "Mannheim" mit einem
| ANDEREN country_id bekam mit 200 den bestehenden Datensatz zurueck — der
| Schadensfall aus Issue #33 selbst (Springfield/Missouri bekam
| Springfield/Illinois). Aufgeteilt in zwei Zusagen, weil es jetzt zwei
| verschiedene sind: dieselbe Kombination Name+Land trifft weiterhin den
| Bestand (unveraendert), eine ABWEICHENDE Kombination scheitert jetzt sichtbar
| statt still den falschen Datensatz zurueckzugeben.
|--------------------------------------------------------------------------
*/

it('returns the existing city instead of duplicating it when the country matches', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Mannheim', 'country_id' => $country->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateCityTool::class, [
            'name' => 'Mannheim',
            'country_id' => $country->id,
            'longitude' => 8.474687,
            'latitude' => 49.498203,
        ])
        ->assertOk()
        ->assertSee('already_existed');

    expect(City::query()->where('name', 'Mannheim')->count())->toBe(1);
});

it('rejects creating a city whose name exists in a different country, instead of returning the wrong match', function () {
    $user = User::factory()->create();
    $otherCountry = Country::factory()->create();
    City::factory()->create(['name' => 'Mannheim']);

    EinundzwanzigServer::actingAs($user)
        ->tool(CreateCityTool::class, [
            'name' => 'Mannheim',
            'country_id' => $otherCountry->id,
            'longitude' => 8.474687,
            'latitude' => 49.498203,
        ])
        ->assertHasErrors();

    // Der eigentliche Schaden aus Issue #33: es darf NICHT still die falsche,
    // bestehende Stadt zurueckgegeben und auch keine zweite ohne Rueckfrage
    // angelegt werden.
    expect(City::query()->where('name', 'Mannheim')->count())->toBe(1);
});

it('fails validation for missing fields', function () {
    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(CreateCityTool::class, [])
        ->assertHasErrors();
});

it('lets the owner update a city', function () {
    $user = User::factory()->create();
    $city = City::factory()->create(['created_by' => $user->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateCityTool::class, ['id' => $city->id, 'name' => 'Nürnberg'])
        ->assertOk()
        ->assertSee('Nürnberg');
});

it('forbids updating someone elses city', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateCityTool::class, ['id' => $city->id, 'name' => 'Hijack'])
        ->assertHasErrors();
});

it('returns only own cities in the mine list', function () {
    $user = User::factory()->create();
    City::factory()->count(2)->create(['created_by' => $user->id]);
    City::factory()->create(['created_by' => User::factory()->create()->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(ListMyCitiesTool::class)
        ->assertOk();
});

it('forbids viewing someone elses city in mine show', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create(['created_by' => $owner->id]);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(ShowMyCityTool::class, ['id' => $city->id])
        ->assertHasErrors();
});

it('resolves a region by name within the citys country and updates population_date', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();
    $region = Region::factory()->create(['country_id' => $country->id, 'name' => 'Bayern']);
    $city = City::factory()->create(['created_by' => $user->id, 'country_id' => $country->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateCityTool::class, [
            'id' => $city->id,
            'region' => 'Bayern',
            'population_date' => '2024',
        ])
        ->assertOk()
        ->assertSee('2024');

    $fresh = $city->fresh();

    expect((int) $fresh->region_id)->toBe($region->id)
        ->and($fresh->population_date)->toBe('2024');
});

/*
|--------------------------------------------------------------------------
| Issue #30 — UpdateCityTool loest global auf, nicht nur ueber die eigenen
| Staedte (resolveGlobalByName statt resolveOwnedByName).
|--------------------------------------------------------------------------
*/

it('finds a foreign city by name and lets a non-creator enrich it', function () {
    $owner = User::factory()->create();
    $city = City::factory()->create(['created_by' => $owner->id, 'name' => 'Findable Foreign City']);

    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateCityTool::class, [
            'city' => 'Findable Foreign City',
            'osm_type' => 'node',
            'osm_id' => 240109189,
        ])
        ->assertOk()
        ->assertSee('Findable Foreign City');

    expect($city->fresh()->osm_id)->toBe(240109189);
});

it('forbids the same foreign city rename by name, not with a not-found error', function () {
    $owner = User::factory()->create();
    City::factory()->create(['created_by' => $owner->id, 'name' => 'Guarded Foreign City']);

    // Die Ablehnung ist eine Berechtigungsgrenze, kein Suchergebnis — die Stadt WURDE
    // gefunden, das Aendern von 'name' scheitert an updateIdentity().
    EinundzwanzigServer::actingAs(User::factory()->create())
        ->tool(UpdateCityTool::class, [
            'city' => 'Guarded Foreign City',
            'name' => 'Renamed',
        ])
        ->assertHasErrors()
        ->assertDontSee('nicht gefunden');
});

it('refuses a region name that only exists in another country', function () {
    $user = User::factory()->create();
    $country = Country::factory()->create();
    $otherCountry = Country::factory()->create();
    Region::factory()->create(['country_id' => $otherCountry->id, 'name' => 'Georgia']);
    $city = City::factory()->create(['created_by' => $user->id, 'country_id' => $country->id]);

    EinundzwanzigServer::actingAs($user)
        ->tool(UpdateCityTool::class, [
            'id' => $city->id,
            'region' => 'Georgia',
        ])
        ->assertHasErrors();

    expect($city->fresh()->region_id)->toBeNull();
});
