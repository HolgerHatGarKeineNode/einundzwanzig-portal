<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('defaults both RSVP settings to true for new meetups', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id])->fresh();

    expect($meetup->rsvp_enabled)->toBeTrue()
        ->and($meetup->attendees_public)->toBeTrue();
});

it('lets the owner toggle the RSVP settings over the API and returns them in the resource', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $meetup = Meetup::factory()->create(['created_by' => $user->id, 'city_id' => $this->city->id]);

    $this->patchJson('/api/meetup/'.$meetup->id, [
        'rsvp_enabled' => false,
        'attendees_public' => false,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.rsvp_enabled', false)
        ->assertJsonPath('data.attendees_public', false);

    expect($meetup->fresh())
        ->rsvp_enabled->toBeFalse()
        ->attendees_public->toBeFalse();
});

it('exposes the RSVP settings on the public map endpoint', function () {
    Meetup::factory()->create([
        'city_id' => $this->city->id,
        'visible_on_map' => true,
        'name' => 'Flag Meetup',
        'rsvp_enabled' => false,
        'attendees_public' => false,
    ]);

    $payload = collect($this->getJson('/api/meetups')->json())->firstWhere('name', 'Flag Meetup');

    expect($payload)
        ->rsvp_enabled->toBeFalse()
        ->attendees_public->toBeFalse();
});

it('rejects an RSVP when the meetup has RSVP disabled', function () {
    Sanctum::actingAs(User::factory()->create());
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'rsvp_enabled' => false]);
    $event = MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);

    $this->postJson("/api/meetup-events/{$event->id}/rsvp", ['status' => 'attending'])
        ->assertUnprocessable();

    expect($event->fresh()->attendees)->toBe([]);
});

it('hides the RSVP counts from a non-manager when attendees are not public', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'attendees_public' => false]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'attendees' => ['id_999|Hal'],
    ]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/meetup-events/{$event->id}/rsvp")
        ->assertSuccessful()
        ->assertJsonPath('attendees', null)
        ->assertJsonPath('might_attendees', null);
});

it('still shows the RSVP counts to the meetup owner when attendees are not public', function () {
    Sanctum::actingAs($owner = User::factory()->create());
    $meetup = Meetup::factory()->create([
        'created_by' => $owner->id,
        'city_id' => $this->city->id,
        'attendees_public' => false,
    ]);
    $event = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'attendees' => ['id_999|Hal'],
    ]);

    $this->getJson("/api/meetup-events/{$event->id}/rsvp")
        ->assertSuccessful()
        ->assertJsonPath('attendees', 1);
});

it('nulls the public event-list counts when attendees are not public', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id, 'attendees_public' => false]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay(),
        'attendees' => ['id_999|Hal'],
    ]);

    $payload = collect($this->getJson('/api/meetup-events')->json())->first();

    expect($payload['attendees'])->toBeNull()
        ->and($payload['might_attendees'])->toBeNull();
});

it('reports attendeesVisibleTo correctly for guests, strangers, and managers', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $public = Meetup::factory()->create(['city_id' => $this->city->id, 'attendees_public' => true, 'created_by' => $owner->id]);
    $private = Meetup::factory()->create(['city_id' => $this->city->id, 'attendees_public' => false, 'created_by' => $owner->id]);

    expect($public->attendeesVisibleTo(null))->toBeTrue()
        ->and($public->attendeesVisibleTo($stranger))->toBeTrue()
        ->and($private->attendeesVisibleTo(null))->toBeFalse()
        ->and($private->attendeesVisibleTo($stranger))->toBeFalse()
        ->and($private->attendeesVisibleTo($owner))->toBeTrue();
});

it('persists the RSVP settings from the edit component', function () {
    RateLimiter::clear(Meetup::updateRateLimitKey(1));
    $creator = actingAsUser();
    $meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $creator->id,
        'rsvp_enabled' => true,
        'attendees_public' => true,
    ]);
    $meetup->users()->attach($creator);

    Livewire::test('meetups.edit', ['meetup' => $meetup])
        ->set('name', $meetup->name)
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->set('rsvp_enabled', false)
        ->set('attendees_public', false)
        ->call('updateMeetup')
        ->assertHasNoErrors();

    expect($meetup->fresh())
        ->rsvp_enabled->toBeFalse()
        ->attendees_public->toBeFalse();
});
