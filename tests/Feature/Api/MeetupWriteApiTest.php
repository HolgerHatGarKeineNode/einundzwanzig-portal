<?php

use App\Models\City;
use App\Models\Meetup;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a guest', function () {
    $this->postJson('/api/meetup', [
        'name' => 'Einundzwanzig Ansbach',
        'city_id' => City::factory()->create()->id,
    ])->assertUnauthorized();
});

it('lets an authenticated user create', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $this->postJson('/api/meetup', [
        'name' => 'Einundzwanzig Ansbach',
        'city_id' => City::factory()->create()->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Einundzwanzig Ansbach');

    $this->assertDatabaseHas('meetups', [
        'name' => 'Einundzwanzig Ansbach',
        'created_by' => $user->id,
    ]);
});

it('fails validation', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/meetup', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'city_id']);
});

it('lets the owner update', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'name' => 'Plan B Lugano',
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.name', 'Plan B Lugano');
});

it('forbids updating someone elses', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'name' => 'Plan B Lugano',
    ])->assertForbidden();
});

it('returns only own in mine index', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $other = User::factory()->create();

    Meetup::factory()->count(2)->create(['created_by' => $user->id]);
    Meetup::factory()->create(['created_by' => $other->id]);

    $response = $this->getJson('/api/my-meetups');

    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(2);
    collect($response->json('data'))->each(
        fn ($meetup) => expect($meetup['created_by'])->toBe($user->id)
    );
});

it('forbids viewing someone elses in mine show', function () {
    $owner = User::factory()->create();
    $meetup = Meetup::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/my-meetups/'.$meetup->id)->assertForbidden();
});
