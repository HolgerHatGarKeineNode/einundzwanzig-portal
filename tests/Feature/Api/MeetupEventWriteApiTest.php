<?php

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a guest', function () {
    $response = $this->postJson('/api/meetup-events', []);

    $response->assertUnauthorized();
});

it('lets a leader of the meetup create an event', function () {
    Sanctum::actingAs($user = User::factory()->create());
    // Ersteller wird per booted-Hook automatisch Leader des Meetups.
    $meetup = Meetup::factory()->create(['created_by' => $user->id]);

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
    ]);

    $response->assertCreated();

    $this->assertDatabaseHas('meetup_events', [
        'location' => 'Marktplatz',
        'created_by' => $user->id,
    ]);
});

it('lets a delegated leader create an event for the meetup', function () {
    $meetup = Meetup::factory()->create(['created_by' => User::factory()->create()->id]);
    $leader = User::factory()->create();
    $meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);

    Sanctum::actingAs($leader);
    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
    ])->assertCreated();
});

it('forbids creating an event for a meetup the user does not lead', function () {
    $meetup = Meetup::factory()->create(['created_by' => User::factory()->create()->id]);
    $member = User::factory()->create();
    $meetup->addMember($member); // is_leader = false

    Sanctum::actingAs($member);
    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
    ])->assertForbidden();
});

it('lets a meetup leader edit an event created by someone else', function () {
    $meetup = Meetup::factory()->create(['created_by' => User::factory()->create()->id]);
    $leader = User::factory()->create();
    $meetup->users()->syncWithoutDetaching([$leader->id => ['is_leader' => true]]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'created_by' => User::factory()->create()->id,
    ]);

    Sanctum::actingAs($leader);
    $this->patchJson("/api/meetup-events/{$event->id}", ['location' => 'Rathaus'])
        ->assertSuccessful()
        ->assertJsonPath('data.location', 'Rathaus');
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

it('returns own and led-meetup events in mine index', function () {
    Sanctum::actingAs($user = User::factory()->create());

    // 2 selbst angelegt
    MeetupEvent::factory()->count(2)->create(['created_by' => $user->id]);

    // 1 Termin eines Co-Leaders im selben (von $user geführten) Meetup -> sichtbar
    $ledMeetup = Meetup::factory()->create();
    $ledMeetup->users()->syncWithoutDetaching([$user->id => ['is_leader' => true]]);
    MeetupEvent::factory()->create([
        'meetup_id' => $ledMeetup->id,
        'created_by' => User::factory()->create()->id,
    ]);

    // 1 fremder Termin in einem Meetup ohne Leaderschaft -> NICHT sichtbar
    MeetupEvent::factory()->create(['created_by' => User::factory()->create()->id]);

    $response = $this->getJson('/api/my-meetup-events');

    $response->assertSuccessful();

    expect($response->json('data'))->toHaveCount(3);
});

it('forbids viewing someone elses in mine show', function () {
    $owner = User::factory()->create();
    $model = MeetupEvent::factory()->create(['created_by' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $response = $this->getJson("/api/my-meetup-events/{$model->id}");

    $response->assertForbidden();
});
