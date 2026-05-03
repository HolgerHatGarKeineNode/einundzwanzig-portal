<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Lecturer;
use App\Models\SelfHostedService;
use App\Models\Venue;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
    $this->venue = Venue::factory()->create(['city_id' => $this->city->id]);
    $this->lecturer = Lecturer::factory()->create();
    $this->service = SelfHostedService::factory()->create();
});

it('mounts lecturers.create when authenticated', function () {
    actingAsUser();
    Livewire::test('lecturers.create')->assertStatus(200);
});

it('mounts lecturers.edit when authenticated as the lecturer creator', function () {
    $owner = actingAsUser();
    $lecturer = Lecturer::factory()->create(['created_by' => $owner->id]);

    Livewire::test('lecturers.edit', ['lecturer' => $lecturer])->assertStatus(200);
});

it('aborts lecturers.edit with 403 when authenticated user is not the creator', function () {
    actingAsUser();

    Livewire::test('lecturers.edit', ['lecturer' => $this->lecturer])->assertStatus(403);
});

it('mounts cities.create when authenticated', function () {
    actingAsUser();
    Livewire::test('cities.create')->assertStatus(200);
});

it('mounts cities.edit when authenticated', function () {
    actingAsUser();
    Livewire::test('cities.edit', ['city' => $this->city])->assertStatus(200);
});

it('mounts venues.create when authenticated', function () {
    actingAsUser();
    Livewire::test('venues.create')->assertStatus(200);
});

it('mounts venues.edit when authenticated', function () {
    actingAsUser();
    Livewire::test('venues.edit', ['venue' => $this->venue])->assertStatus(200);
});

it('mounts services.create when authenticated', function () {
    actingAsUser();
    Livewire::test('services.create')->assertStatus(200);
});

it('mounts services.edit when authenticated as the service creator', function () {
    $owner = actingAsUser();
    $service = SelfHostedService::factory()->create(['created_by' => $owner->id]);

    Livewire::test('services.edit', ['service' => $service])->assertStatus(200);
});

it('aborts services.edit with 403 when authenticated user is not the creator', function () {
    actingAsUser();

    Livewire::test('services.edit', ['service' => $this->service])->assertStatus(403);
});

it('mounts services.landingpage with a service', function () {
    Livewire::test('services.landingpage', ['service' => $this->service])->assertStatus(200);
});
