<?php

use App\Models\City;
use App\Models\Country;
use Livewire\Livewire;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de']);
});

it('creates a City with valid data', function () {
    actingAsUser();

    Livewire::test('cities.create')
        ->set('name', 'Berlin')
        ->set('country_id', $this->country->id)
        ->set('latitude', 52.52)
        ->set('longitude', 13.405)
        ->call('createCity')
        ->assertHasNoErrors();

    expect(City::query()->where('name', 'Berlin')->exists())->toBeTrue();
});

it('rejects city creation when required fields are blank (country_id is preset by mount() from the route prefix)', function () {
    actingAsUser();

    Livewire::test('cities.create')
        ->call('createCity')
        ->assertHasErrors([
            'name' => 'required',
            'latitude' => 'required',
            'longitude' => 'required',
        ]);
});

it('does not crash with PropertyNotFoundException when latitude is set to null', function () {
    actingAsUser();
    Livewire::test('cities.create')
        ->set('latitude', null)
        ->assertStatus(200)
        ->assertSet('latitude', null);
});

it('does not crash with PropertyNotFoundException when longitude is set to null', function () {
    actingAsUser();
    Livewire::test('cities.create')
        ->set('longitude', null)
        ->assertStatus(200)
        ->assertSet('longitude', null);
});

it('rejects city creation when country_id is explicitly cleared', function () {
    actingAsUser();

    Livewire::test('cities.create')
        ->set('name', 'No Country City')
        ->set('country_id', null)
        ->set('latitude', 1)
        ->set('longitude', 1)
        ->call('createCity')
        ->assertHasErrors(['country_id' => 'required']);
});

it('rejects city creation with out-of-range latitude', function () {
    actingAsUser();

    Livewire::test('cities.create')
        ->set('name', 'Bad Lat')
        ->set('country_id', $this->country->id)
        ->set('latitude', 150)
        ->set('longitude', 0)
        ->call('createCity')
        ->assertHasErrors(['latitude' => 'between']);
});

it('rejects city creation with non-existent country', function () {
    actingAsUser();

    Livewire::test('cities.create')
        ->set('name', 'No Country')
        ->set('country_id', 999999)
        ->set('latitude', 0)
        ->set('longitude', 0)
        ->call('createCity')
        ->assertHasErrors(['country_id' => 'exists']);
});

it('updates an existing city', function () {
    $city = City::factory()->create(['name' => 'Old Name', 'country_id' => $this->country->id]);
    actingAsUser();

    Livewire::test('cities.edit', ['city' => $city])
        ->set('name', 'New Name')
        ->set('country_id', $this->country->id)
        ->set('latitude', 52.52)
        ->set('longitude', 13.405)
        ->call('updateCity')
        ->assertHasNoErrors();

    expect($city->refresh()->name)->toBe('New Name');
});
