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
        ->set('osmPlace', cityOsmPlace())
        ->call('createCity')
        ->assertHasNoErrors();

    $city = City::query()->where('name', 'Berlin')->first();

    expect($city)->not->toBeNull()
        ->and((float) $city->latitude)->toBe(52.5173885)
        ->and((float) $city->longitude)->toBe(13.3951309)
        ->and($city->osm_id)->toBe(62422);
});

it('rejects city creation when required fields are blank (country_id is preset by mount() from the route prefix)', function () {
    actingAsUser();

    Livewire::test('cities.create')
        ->call('createCity')
        ->assertHasErrors([
            'name' => 'required',
            'osmPlace.osm_id' => 'required',
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
        ->set('osmPlace', cityOsmPlace())
        ->call('createCity')
        ->assertHasErrors(['country_id' => 'required']);
});

it('rejects city creation when the osm place has no usable coordinates', function () {
    actingAsUser();

    Livewire::test('cities.create')
        ->set('name', 'Bad Lat')
        ->set('country_id', $this->country->id)
        ->set('osmPlace', cityOsmPlace(['osm_lat' => null, 'osm_lon' => null]))
        ->call('createCity')
        ->assertHasErrors(['osmPlace.osm_id']);

    expect(City::query()->where('name', 'Bad Lat')->exists())->toBeFalse();
});

it('rejects city creation with non-existent country', function () {
    actingAsUser();

    Livewire::test('cities.create')
        ->set('name', 'No Country')
        ->set('country_id', 999999)
        ->set('osmPlace', cityOsmPlace())
        ->call('createCity')
        ->assertHasErrors(['country_id' => 'exists']);
});

it('rejects city creation when latitude and longitude are both zero', function () {
    actingAsUser();

    Livewire::test('cities.create')
        ->set('name', 'Null Island')
        ->set('country_id', $this->country->id)
        ->set('osmPlace', cityOsmPlace(['osm_lat' => 0, 'osm_lon' => 0]))
        ->call('createCity')
        ->assertHasErrors(['osmPlace.osm_id']);

    expect(City::query()->where('name', 'Null Island')->exists())->toBeFalse();
});

it('rejects city update when latitude and longitude are both zero', function () {
    $city = City::factory()->create([
        'name' => 'Berlin Test',
        'country_id' => $this->country->id,
        'latitude' => 52.52,
        'longitude' => 13.405,
    ]);
    actingAsUser();

    Livewire::test('cities.edit', ['city' => $city])
        ->set('name', 'Berlin Test')
        ->set('country_id', $this->country->id)
        ->set('osmPlace', cityOsmPlace(['osm_lat' => 0, 'osm_lon' => 0]))
        ->call('updateCity')
        ->assertHasErrors(['osmPlace.osm_id']);

    expect($city->refresh()->latitude)->toEqual(52.52);
});

it('updates an existing city', function () {
    // Issue #30: name ist ein Identitaetsfeld — nur der Ersteller (oder ein
    // City-Steward/Super-Admin) darf es aendern. Dieser Test prueft den Schreibpfad
    // selbst, nicht die Berechtigungsgrenze, darum handelt hier bewusst der Ersteller.
    $user = actingAsUser();
    $city = City::factory()->create(['name' => 'Old Name', 'country_id' => $this->country->id, 'created_by' => $user->id]);

    Livewire::test('cities.edit', ['city' => $city])
        ->set('name', 'New Name')
        ->set('country_id', $this->country->id)
        ->set('osmPlace', cityOsmPlace())
        ->call('updateCity')
        ->assertHasNoErrors();

    expect($city->refresh()->name)->toBe('New Name');
});
