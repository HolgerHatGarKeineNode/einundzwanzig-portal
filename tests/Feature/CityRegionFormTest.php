<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->us = Country::factory()->create(['code' => 'us']);
    $this->indiana = Region::factory()->indiana()->create(['country_id' => $this->us->id]);

    // Land ohne gepflegte Regionen — dort darf sich am Formular nichts aendern.
    $this->austria = Country::factory()->create(['code' => 'at']);
});

it('stores the region when creating a city', function () {
    Livewire::test('cities.create')
        ->set('name', 'Fort Wayne')
        ->set('country_id', $this->us->id)
        ->set('region_id', $this->indiana->id)
        ->set('latitude', 41.0793)
        ->set('longitude', -85.1394)
        ->call('createCity')
        ->assertHasNoErrors();

    expect(City::firstWhere('name', 'Fort Wayne')?->region_id)->toBe($this->indiana->id);
});

it('creates a city without a region exactly as before', function () {
    Livewire::test('cities.create')
        ->set('name', 'Salzburg Test')
        ->set('country_id', $this->austria->id)
        ->set('latitude', 47.8095)
        ->set('longitude', 13.0550)
        ->call('createCity')
        ->assertHasNoErrors();

    expect(City::firstWhere('name', 'Salzburg Test')?->region_id)->toBeNull();
});

it('rejects a region that belongs to another country', function () {
    Livewire::test('cities.create')
        ->set('name', 'Wrong Region City')
        ->set('country_id', $this->austria->id)
        ->set('region_id', $this->indiana->id)
        ->set('latitude', 47.8095)
        ->set('longitude', 13.0550)
        ->call('createCity')
        ->assertHasErrors('region_id');

    expect(City::firstWhere('name', 'Wrong Region City'))->toBeNull();
});

it('clears the region when the country changes', function () {
    Livewire::test('cities.create')
        ->set('country_id', $this->us->id)
        ->set('region_id', $this->indiana->id)
        ->set('country_id', $this->austria->id)
        ->assertSet('region_id', null);
});

it('offers regions only for countries that have them', function () {
    Livewire::test('cities.create')
        ->set('country_id', $this->austria->id)
        ->assertViewHas('regions', fn ($regions) => $regions->isEmpty())
        ->set('country_id', $this->us->id)
        ->assertViewHas('regions', fn ($regions) => $regions->count() === 1);
});

it('keeps and updates the region when editing a city', function () {
    // Issue #30: region_id ist ein Identitaetsfeld. Diese Person legt die Stadt hier
    // selbst an, weil der Test den Region-Schreibpfad prueft, nicht die
    // Berechtigungsgrenze — die deckt CityIdentityGuardTest ab.
    $city = City::factory()->create([
        'country_id' => $this->us->id,
        'region_id' => null,
        'name' => 'Evansville',
        'created_by' => $this->user->id,
    ]);

    Livewire::test('cities.edit', ['city' => $city])
        ->assertSet('region_id', null)
        ->set('region_id', $this->indiana->id)
        ->call('updateCity')
        ->assertHasNoErrors();

    expect($city->fresh()->region_id)->toBe($this->indiana->id);
});
