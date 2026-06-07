<?php

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a guest', function () {
    $response = $this->postJson('/api/meetup-events', []);

    $response->assertUnauthorized();
});

it('lets an authenticated user create', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => Meetup::factory()->create()->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('meetup_events', [
        'location' => 'Marktplatz',
        'created_by' => $user->id,
    ]);
});

it('fails validation', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/meetup-events', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['meetup_id', 'start']);
});

it('lets the owner update', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $model = MeetupEvent::factory()->create(['created_by' => $user->id]);

    $response = $this->patchJson("/api/meetup-events/{$model->id}", [
        'location' => 'Rathaus',
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.location', 'Rathaus');
});

it('forbids updating someone elses', function () {
    $owner = User::factory()->create();
    $model = MeetupEvent::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->patchJson("/api/meetup-events/{$model->id}", [
        'location' => 'Rathaus',
    ]);

    $response->assertForbidden();
});

it('returns only own in mine index', function () {
    Sanctum::actingAs($user = User::factory()->create());

    MeetupEvent::factory()->count(2)->create(['created_by' => $user->id]);
    MeetupEvent::factory()->create(['created_by' => User::factory()->create()->id]);

    $response = $this->getJson('/api/my-meetup-events');

    $response->assertSuccessful();

    expect($response->json('data'))->toHaveCount(2);
});

it('forbids viewing someone elses in mine show', function () {
    $owner = User::factory()->create();
    $model = MeetupEvent::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson("/api/my-meetup-events/{$model->id}");

    $response->assertForbidden();
});
