<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Venue;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('creates a Venue with valid data', function () {
    actingAsUser();

    Livewire::test('venues.create')
        ->set('name', 'Bitcoin Hub Berlin')
        ->set('city_id', $this->city->id)
        ->set('street', 'Lichtenberger Str. 1')
        ->call('createVenue')
        ->assertHasNoErrors();

    expect(Venue::query()->where('name', 'Bitcoin Hub Berlin')->exists())->toBeTrue();
});

it('rejects venue creation without required fields', function () {
    actingAsUser();

    Livewire::test('venues.create')
        ->call('createVenue')
        ->assertHasErrors([
            'name' => 'required',
            'city_id' => 'required',
            'street' => 'required',
        ]);
});

it('rejects venue creation with duplicate name', function () {
    Venue::factory()->create(['name' => 'Existing Venue', 'city_id' => $this->city->id]);
    actingAsUser();

    Livewire::test('venues.create')
        ->set('name', 'Existing Venue')
        ->set('city_id', $this->city->id)
        ->set('street', 'Some Street')
        ->call('createVenue')
        ->assertHasErrors(['name' => 'unique']);
});

it('updates an existing venue', function () {
    $venue = Venue::factory()->create(['name' => 'Old Name', 'city_id' => $this->city->id]);
    actingAsUser();

    Livewire::test('venues.edit', ['venue' => $venue])
        ->set('name', 'New Name')
        ->set('city_id', $this->city->id)
        ->set('street', 'New Street 1')
        ->call('updateVenue')
        ->assertHasNoErrors();

    expect($venue->refresh()->name)->toBe('New Name');
});
