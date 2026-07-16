<?php

use App\Models\MeetupEvent;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects a guest', function () {
    $event = MeetupEvent::factory()->create();

    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'attending'])
        ->assertUnauthorized();
});

it('lets an authenticated user attend', function () {
    Sanctum::actingAs($user = User::factory()->create(['name' => 'Satoshi']));
    $event = MeetupEvent::factory()->create();

    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'attending'])
        ->assertSuccessful()
        ->assertJson(['status' => 'attending', 'attendees' => 1, 'might_attendees' => 0]);

    expect($event->fresh()->attendees)->toBe(["id_{$user->id}|Satoshi"]);
});

it('moves the user between lists without duplicating', function () {
    Sanctum::actingAs($user = User::factory()->create(['name' => 'Satoshi']));
    $event = MeetupEvent::factory()->create();

    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'attending']);
    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'maybe'])
        ->assertJson(['status' => 'maybe', 'attendees' => 0, 'might_attendees' => 1]);

    $event->refresh();
    expect($event->attendees)->toBe([])
        ->and($event->might_attendees)->toBe(["id_{$user->id}|Satoshi"]);
});

it('withdraws the user with status none', function () {
    Sanctum::actingAs($user = User::factory()->create(['name' => 'Satoshi']));
    $event = MeetupEvent::factory()->create();

    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'attending']);
    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'none'])
        ->assertJson(['status' => 'none', 'attendees' => 0, 'might_attendees' => 0]);

    expect($event->fresh()->attendees)->toBe([]);
});

it('keeps other attendees untouched', function () {
    $event = MeetupEvent::factory()->create([
        'attendees' => ['id_999|Hal', 'id_50|Nick'],
    ]);
    Sanctum::actingAs($user = User::factory()->create(['name' => 'Satoshi']));

    // id_5 darf id_50 nicht versehentlich treffen (Prefix-Abgrenzung per Pipe).
    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'attending'])
        ->assertJson(['attendees' => 3]);

    expect($event->fresh()->attendees)
        ->toContain('id_999|Hal')
        ->toContain('id_50|Nick')
        ->toContain("id_{$user->id}|Satoshi");
});

it('reports the current status and counts', function () {
    Sanctum::actingAs($user = User::factory()->create(['name' => 'Satoshi']));
    $event = MeetupEvent::factory()->create([
        'attendees' => ['id_999|Hal'],
        'might_attendees' => ["id_{$user->id}|Satoshi"],
    ]);

    $this->getJson("/api/meetup-events/{$event->id}/rsvp")
        ->assertSuccessful()
        ->assertJson(['status' => 'maybe', 'attendees' => 1, 'might_attendees' => 1]);
});

it('exposes attendee display names without the id prefix', function () {
    Sanctum::actingAs(User::factory()->create());
    $event = MeetupEvent::factory()->create([
        'attendees' => ['id_999|Hal', 'id_50|Nick'],
    ]);

    $this->getJson("/api/meetup-events/{$event->id}/rsvp")
        ->assertSuccessful()
        ->assertJson(['attendee_names' => ['Hal', 'Nick']]);
});

it('hides attendee names when the list is not public', function () {
    Sanctum::actingAs(User::factory()->create());
    $event = MeetupEvent::factory()->create([
        'attendees' => ['id_999|Hal'],
    ]);
    $event->meetup->update(['attendees_public' => false]);

    $this->getJson("/api/meetup-events/{$event->id}/rsvp")
        ->assertSuccessful()
        ->assertJson(['attendees' => null, 'attendee_names' => null]);
});

it('validates the status value', function () {
    Sanctum::actingAs(User::factory()->create());
    $event = MeetupEvent::factory()->create();

    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'bogus'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});
