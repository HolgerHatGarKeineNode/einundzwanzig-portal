<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
    $this->city = City::factory()->create([
        'country_id' => $this->country->id,
        'name' => 'BrowserMeetupTestCity',
    ]);
});

it('creates a new meetup end-to-end via the create form', function () {
    actingAsUser();
    $cityId = $this->city->id;

    $page = visit('/de/meetup-create');

    $page->fill('[wire\\:model="name"]', 'BrowserSeeded Meetup');
    $page->script("Livewire.getByName('meetups.create')[0].set('city_id', {$cityId})");
    $page->wait(0.5)
        ->select('[wire\\:model="community"]', 'einundzwanzig');
    $page->script("Livewire.getByName('meetups.create')[0].call('createMeetup')");
    $page->wait(2)
        ->assertNoJavaScriptErrors();

    expect(Meetup::query()->where('name', 'BrowserSeeded Meetup')->exists())->toBeTrue();
});

it('blocks meetup creation when the name is missing', function () {
    actingAsUser();
    $cityId = $this->city->id;

    $page = visit('/de/meetup-create');

    $page->script("Livewire.getByName('meetups.create')[0].set('city_id', {$cityId})");
    $page->wait(0.5)
        ->select('[wire\\:model="community"]', 'einundzwanzig');
    $page->script("Livewire.getByName('meetups.create')[0].call('createMeetup')");
    $page->wait(1)
        ->assertNoJavaScriptErrors();

    expect(Meetup::query()->count())->toBe(0);
});

it('blocks meetup creation when no city is selected', function () {
    actingAsUser();

    $page = visit('/de/meetup-create');

    $page->fill('[wire\\:model="name"]', 'NoCityMeetup')
        ->select('[wire\\:model="community"]', 'einundzwanzig');
    $page->script("Livewire.getByName('meetups.create')[0].call('createMeetup')");
    $page->wait(1)
        ->assertNoJavaScriptErrors();

    expect(Meetup::query()->where('name', 'NoCityMeetup')->exists())->toBeFalse();
});
