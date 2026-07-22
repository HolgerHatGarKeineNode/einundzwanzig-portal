<?php

use App\Models\City;
use App\Models\Country;
use App\Models\User;
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

it('returns the existing city instead of duplicating it', function () {
    Sanctum::actingAs(User::factory()->create());

    $existing = City::factory()->create(['name' => 'Mannheim']);

    $response = $this->postJson('/api/cities', [
        'name' => 'Mannheim',
        'country_id' => Country::factory()->create()->id,
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

    $existing = City::factory()->create(['name' => 'Mannheim']);

    $response = $this->postJson('/api/cities', [
        'name' => 'mannheim',
        'country_id' => Country::factory()->create()->id,
        'longitude' => 8.474687,
        'latitude' => 49.498203,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $existing->id);

    expect(City::query()->count())->toBe(1);
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
