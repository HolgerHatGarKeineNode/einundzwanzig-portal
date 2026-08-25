<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

it('rejects a guest', function () {
    $response = $this->postJson('/api/cities', [
        'name' => 'Ansbach',
        'country_id' => Country::factory()->create()->id,
    ]);

    $response->assertUnauthorized();
});

it('lets an authenticated user create', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $response = $this->postJson('/api/cities', [
        'name' => 'Ansbach',
        'country_id' => Country::factory()->create()->id,
        'longitude' => 10.5806,
        'latitude' => 49.3034,
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('cities', [
        'name' => 'Ansbach',
        'created_by' => $user->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Issue #33 — resolveOrCreate() loest ueber Name + Land auf, nicht mehr ueber
| den Namen allein. Die beiden Tests unten waren bis 2026-08-25 falsch, ohne
| aufzufallen: sie schickten fuer die "existierende Stadt" IMMER ein frisch
| erzeugtes, damit zwangslaeufig ABWEICHENDES country_id mit
| (`Country::factory()->create()->id` ist bei jedem Aufruf ein neues Land) —
| unter altem Verhalten war das egal, weil der Name allein entschied. Unter
| neuem Verhalten heisst ein abweichendes Land: KEIN Treffer, siehe den Test
| direkt darunter ("rejects ... different country"). Fix: dasselbe Land fuer
| Bestand und Anfrage, explizit.
|--------------------------------------------------------------------------
*/

it('returns the existing city instead of duplicating it', function () {
    Sanctum::actingAs(User::factory()->create());

    $country = Country::factory()->create();
    $existing = City::factory()->create(['name' => 'Mannheim', 'country_id' => $country->id]);

    $response = $this->postJson('/api/cities', [
        'name' => 'Mannheim',
        'country_id' => $country->id,
        'longitude' => 8.474687,
        'latitude' => 49.498203,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $existing->id)
        ->assertJsonPath('data.name', 'Mannheim');

    expect(City::query()->where('name', 'Mannheim')->count())->toBe(1);
});

it('matches an existing city case insensitively', function () {
    Sanctum::actingAs(User::factory()->create());

    $country = Country::factory()->create();
    $existing = City::factory()->create(['name' => 'Mannheim', 'country_id' => $country->id]);

    $response = $this->postJson('/api/cities', [
        'name' => 'mannheim',
        'country_id' => $country->id,
        'longitude' => 8.474687,
        'latitude' => 49.498203,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $existing->id);

    expect(City::query()->count())->toBe(1);
});

it('rejects a name that exists in a different country, instead of returning the wrong match', function () {
    Sanctum::actingAs(User::factory()->create());

    City::factory()->create(['name' => 'Mannheim']);
    $otherCountry = Country::factory()->create();

    $response = $this->postJson('/api/cities', [
        'name' => 'Mannheim',
        'country_id' => $otherCountry->id,
        'longitude' => 8.474687,
        'latitude' => 49.498203,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    expect(City::query()->where('name', 'Mannheim')->count())->toBe(1);
});

it('fails validation', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/cities', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'country_id']);
});

it('lets the owner update', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $model = City::factory()->create(['created_by' => $user->id]);

    $response = $this->patchJson("/api/cities/{$model->id}", [
        'name' => 'Nürnberg',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Nürnberg');
});

it('forbids updating someone elses', function () {
    $owner = User::factory()->create();
    $model = City::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->patchJson("/api/cities/{$model->id}", [
        'name' => 'Nürnberg',
    ]);

    $response->assertForbidden();
});

it('returns only own in mine index', function () {
    Sanctum::actingAs($user = User::factory()->create());

    City::factory()->create(['created_by' => $user->id]);
    City::factory()->create(['created_by' => $user->id]);
    City::factory()->create(['created_by' => User::factory()->create()->id]);

    $response = $this->getJson('/api/my-cities');

    $response->assertSuccessful();

    expect($response->json('data'))->toHaveCount(2);
});

it('forbids viewing someone elses in mine show', function () {
    $owner = User::factory()->create();
    $model = City::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson("/api/my-cities/{$model->id}");

    $response->assertForbidden();
});

/*
|--------------------------------------------------------------------------
| Issue #30 — Drosselung, Namens-Unique und Region/Land-Konsistenz
|--------------------------------------------------------------------------
*/

it('applies throttle:60,1 to every route in the sanctum write group', function () {
    foreach (['api.cities.store', 'api.cities.update', 'api.courses.store', 'api.lecturers.store', 'api.meetup.store'] as $name) {
        $route = Route::getRoutes()->getByName($name);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('throttle:60,1');
    }
});

it('throttles an authenticated user after 60 requests to the write group', function () {
    Sanctum::actingAs(User::factory()->create());

    // Leere Payload: schlaegt zuverlaessig und billig mit 422 fehl, bevor irgendein
    // Datensatz angelegt wird — die Middleware zaehlt trotzdem mit, weil `throttle`
    // vor der FormRequest-Validierung im Pipeline steht.
    for ($i = 0; $i < 60; $i++) {
        $this->postJson('/api/cities', [])->assertStatus(422);
    }

    $this->postJson('/api/cities', [])->assertStatus(429);
});

it('rejects renaming to a name already used by another city in the same country, with 422, not 500', function () {
    Sanctum::actingAs($user = User::factory()->create());

    // Issue #33: `name.unique` auf UpdateCityRequest ist seit 2026-08-25 landesbezogen
    // (Rule::unique('cities','name')->where('country_id', ...)), nicht mehr global. Bis
    // dahin genuegte irgendein zweites 'Regensburg' irgendwo; jetzt muss es im SELBEN
    // Land liegen, sonst kollidiert die Regel gar nicht mehr (siehe den Test direkt
    // darunter, der genau das prueft).
    $country = Country::factory()->create();
    City::factory()->create(['name' => 'Regensburg', 'country_id' => $country->id]);
    $mine = City::factory()->create(['created_by' => $user->id, 'name' => 'Ansbach', 'country_id' => $country->id]);

    $response = $this->patchJson("/api/cities/{$mine->id}", [
        'name' => 'Regensburg',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['name']);

    expect($mine->fresh()->name)->toBe('Ansbach');
});

it('allows renaming to a name already used by another city in a DIFFERENT country', function () {
    Sanctum::actingAs($user = User::factory()->create());

    City::factory()->create(['name' => 'Regensburg']);
    $mine = City::factory()->create(['created_by' => $user->id, 'name' => 'Ansbach']);

    $response = $this->patchJson("/api/cities/{$mine->id}", [
        'name' => 'Regensburg',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Regensburg');

    expect($mine->fresh()->name)->toBe('Regensburg');
});

it('lets a city keep its own name unchanged on update', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $mine = City::factory()->create(['created_by' => $user->id, 'name' => 'Ansbach']);

    $response = $this->patchJson("/api/cities/{$mine->id}", [
        'name' => 'Ansbach',
        'population' => 55000,
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Ansbach')
        ->assertJsonPath('data.population', 55000);
});

it('creates and updates a city with region_id and population_date, both readable back', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $country = Country::factory()->create();
    $region = Region::factory()->create(['country_id' => $country->id]);

    $response = $this->postJson('/api/cities', [
        'name' => 'Regensburg',
        'country_id' => $country->id,
        'region_id' => $region->id,
        'longitude' => 12.0989,
        'latitude' => 49.0134,
        'population_date' => '2024',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.region_id', $region->id)
        ->assertJsonPath('data.population_date', '2024');

    $city = City::query()->where('name', 'Regensburg')->sole();

    expect($city->created_by)->toBe($user->id);

    $update = $this->patchJson("/api/cities/{$city->id}", [
        'population_date' => '2011-05-09',
    ]);

    $update->assertSuccessful()
        ->assertJsonPath('data.population_date', '2011-05-09')
        ->assertJsonPath('data.region_id', $region->id);
});

it('rejects a region that belongs to a different country', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $country = Country::factory()->create();
    $otherCountry = Country::factory()->create();
    $city = City::factory()->create(['created_by' => $user->id, 'country_id' => $country->id]);
    $foreignRegion = Region::factory()->create(['country_id' => $otherCountry->id]);

    $response = $this->patchJson("/api/cities/{$city->id}", [
        'region_id' => $foreignRegion->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['region_id']);

    expect($city->fresh()->region_id)->toBeNull();
});

it('rejects a region from the old country when country_id changes in the same request', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $oldCountry = Country::factory()->create();
    $newCountry = Country::factory()->create();
    $oldRegion = Region::factory()->create(['country_id' => $oldCountry->id]);
    $city = City::factory()->create(['created_by' => $user->id, 'country_id' => $oldCountry->id]);

    $response = $this->patchJson("/api/cities/{$city->id}", [
        'country_id' => $newCountry->id,
        'region_id' => $oldRegion->id,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['region_id']);

    expect($city->fresh())
        ->country_id->toBe($oldCountry->id)
        ->region_id->toBeNull();
});
