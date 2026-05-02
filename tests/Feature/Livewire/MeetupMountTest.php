<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use Livewire\Livewire;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $this->country->id]);
    $this->meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    $this->event = MeetupEvent::factory()->create(['meetup_id' => $this->meetup->id]);
});

it('mounts meetups.landingpage with a meetup', function () {
    Livewire::test('meetups.landingpage', ['meetup' => $this->meetup])->assertStatus(200);
});

it('mounts meetups.landingpage-event with meetup and event', function () {
    Livewire::test('meetups.landingpage-event', [
        'meetup' => $this->meetup,
        'event' => $this->event,
    ])->assertStatus(200);
});

it('mounts meetups.create when authenticated', function () {
    actingAsUser();
    Livewire::test('meetups.create')->assertStatus(200);
});

it('mounts meetups.edit when authenticated', function () {
    actingAsUser();
    Livewire::test('meetups.edit', ['meetup' => $this->meetup])->assertStatus(200);
});

it('mounts meetups.create-edit-events for new event', function () {
    actingAsUser();
    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])->assertStatus(200);
});

it('mounts meetups.create-edit-events for existing event', function () {
    actingAsUser();
    Livewire::test('meetups.create-edit-events', [
        'meetup' => $this->meetup,
        'event' => $this->event,
    ])->assertStatus(200);
});

it('does not crash with PropertyNotFoundException when startDate is set to null in series mode', function () {
    actingAsUser();
    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('endDate', '2026-10-27')
        ->set('startDate', null)
        ->assertStatus(200)
        ->assertSet('startDate', null);
});

it('does not crash when endDate is set to null in series mode', function () {
    actingAsUser();
    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('seriesMode', true)
        ->set('endDate', null)
        ->assertStatus(200)
        ->assertSet('endDate', null);
});

it('does not crash when startTime is set to null', function () {
    actingAsUser();
    Livewire::test('meetups.create-edit-events', ['meetup' => $this->meetup])
        ->set('startTime', null)
        ->assertStatus(200)
        ->assertSet('startTime', null);
});
