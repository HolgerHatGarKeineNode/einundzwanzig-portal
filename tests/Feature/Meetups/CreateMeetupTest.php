<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('creates a Meetup when authenticated user submits a valid form', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'Berlin Bitcoin Meetup')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasNoErrors()
        ->assertRedirect();

    $meetup = Meetup::query()->where('name', 'Berlin Bitcoin Meetup')->first();
    expect($meetup)->not->toBeNull()
        ->and($meetup->city_id)->toBe($this->city->id);
});

it('rejects creation without a name', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasErrors(['name' => 'required']);
});

it('rejects creation without city_id', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'No City Meetup')
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasErrors(['city_id' => 'required']);
});

it('rejects creation with non-existent city_id', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'Bad City Meetup')
        ->set('city_id', 999999)
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasErrors(['city_id' => 'exists']);
});

it('rejects creation with a duplicate meetup name', function () {
    Meetup::factory()->create(['name' => 'Already Exists', 'city_id' => $this->city->id]);
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'Already Exists')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->call('createMeetup')
        ->assertHasErrors(['name' => 'unique']);
});

it('rejects creation when telegram_link is not a valid URL', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('name', 'Bad URL Meetup')
        ->set('city_id', $this->city->id)
        ->set('community', 'einundzwanzig')
        ->set('telegram_link', 'not-a-url')
        ->call('createMeetup')
        ->assertHasErrors(['telegram_link' => 'url']);
});

it('redirects guests to login when accessing meetup-create', function () {
    $this->get('/de/meetup-create')->assertRedirect(route('login'));
});

it('creates a city via createCity within the meetup-create flow', function () {
    actingAsUser();

    Livewire::test('meetups.create')
        ->set('newCityName', 'Hamburg')
        ->set('newCityCountryId', $this->city->country_id)
        ->set('newCityLatitude', 53.5511)
        ->set('newCityLongitude', 9.9937)
        ->call('createCity')
        ->assertHasNoErrors();

    expect(City::query()->where('name', 'Hamburg')->exists())->toBeTrue();
});
