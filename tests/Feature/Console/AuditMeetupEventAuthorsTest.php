<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->admin = User::factory()->create();
    // Meetup aus dem GitHub-Import: gehört dem Admin, nicht dem Organisator.
    $this->meetup = Meetup::factory()->create([
        'city_id' => $this->city->id,
        'created_by' => $this->admin->id,
    ]);
});

it('reports an event author who is neither leader nor meetup creator', function () {
    $organizer = User::factory()->create(['name' => 'dragsugg']);
    $this->meetup->addMember($organizer); // is_leader = false
    MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $organizer->id,
    ]);

    $this->artisan('meetups:audit-event-authors')
        ->expectsOutputToContain('dragsugg')
        ->assertSuccessful();

    // Ohne --fix wird nichts geschrieben.
    expect($this->meetup->fresh()->isLeader($organizer))->toBeFalse();
});

it('promotes affected authors to leaders with --fix', function () {
    $organizer = User::factory()->create();
    $this->meetup->addMember($organizer);
    MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $organizer->id,
    ]);

    $this->artisan('meetups:audit-event-authors --fix')->assertSuccessful();

    expect($this->meetup->fresh()->isLeader($organizer))->toBeTrue();
});

it('ignores authors who are already leaders or the meetup creator', function () {
    $leader = User::factory()->create();
    $this->meetup->promoteLeader($leader);
    MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $leader->id,
    ]);
    MeetupEvent::factory()->create([
        'meetup_id' => $this->meetup->id,
        'created_by' => $this->admin->id,
    ]);

    $this->artisan('meetups:audit-event-authors')
        ->expectsOutputToContain('Keine betroffenen Termin-Autoren gefunden.')
        ->assertSuccessful();
});
