<?php

use App\Models\City;
use App\Models\User;
use App\Models\Venue;
use Laravel\Sanctum\Sanctum;

it('rejects a guest', function () {
    $this->postJson('/api/venues', [
        'name' => 'Bitcoin Hub',
        'city_id' => City::factory()->create()->id,
    ])->assertUnauthorized();
});

it('lets an authenticated user create', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $this->postJson('/api/venues', [
        'name' => 'Bitcoin Hub',
        'city_id' => City::factory()->create()->id,
        'street' => 'Satoshi Street 21',
    ])->assertCreated();

    $this->assertDatabaseHas('venues', [
        'name' => 'Bitcoin Hub',
        'created_by' => $user->id,
    ]);
});

it('fails validation', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/venues', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'city_id']);
});

it('lets the owner update', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $model = Venue::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/venues/'.$model->id, [
        'name' => 'Orange Hub',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Orange Hub');
});

it('forbids updating someone elses', function () {
    $owner = User::factory()->create();
    $model = Venue::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/venues/'.$model->id, [
        'name' => 'Orange Hub',
    ])->assertForbidden();
});

it('returns only own in mine index', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $other = User::factory()->create();

    Venue::factory()->count(2)->create(['created_by' => $user->id]);
    Venue::factory()->create(['created_by' => $other->id]);

    $response = $this->getJson('/api/my-venues');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);
    collect($response->json('data'))->each(
        fn ($venue) => expect($venue['created_by'])->toBe($user->id)
    );
});

it('forbids viewing someone elses in mine show', function () {
    $owner = User::factory()->create();
    $model = Venue::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/my-venues/'.$model->id)->assertForbidden();
});
