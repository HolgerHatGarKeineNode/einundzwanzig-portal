<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Venue;

beforeEach(function () {
    $this->country = Country::factory()->create(['code' => 'de', 'name' => 'Deutschland']);
});

it('creates a new city end-to-end with valid coordinates', function () {
    actingAsUser();

    $page = visit('/de/city-create');

    $page->fill('[wire\\:model="name"]', 'BrowserTestCity')
        ->fill('[wire\\:model="latitude"]', '52.520008')
        ->fill('[wire\\:model="longitude"]', '13.404954')
        ->click('[data-flux-button][type="submit"]')
        ->wait(2)
        ->assertNoJavaScriptErrors();

    expect(City::query()->where('name', 'BrowserTestCity')->exists())->toBeTrue();
});

it('shows validation errors when city form is submitted empty', function () {
    actingAsUser();

    $page = visit('/de/city-create');

    $page->click('[data-flux-button][type="submit"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(City::query()->count())->toBe(0);
});

it('creates a new venue connected to an existing city', function () {
    actingAsUser();
    $city = City::factory()->create(['country_id' => $this->country->id, 'name' => 'VenueTestCity']);

    $page = visit('/de/venue-create');

    $page->fill('[wire\\:model="name"]', 'BrowserTestVenue')
        ->fill('[wire\\:model="street"]', 'Teststraße 1');
    $page->script("Livewire.getByName('venues.create')[0].set('city_id', {$city->id})");
    $page->wait(0.5)
        ->click('[data-flux-button][type="submit"]')
        ->wait(2)
        ->assertNoJavaScriptErrors();

    expect(Venue::query()->where('name', 'BrowserTestVenue')->exists())->toBeTrue();
});

it('shows a validation error when creating a venue without a name', function () {
    actingAsUser();
    City::factory()->create(['country_id' => $this->country->id]);

    $page = visit('/de/venue-create');

    $page->fill('[wire\\:model="street"]', 'No-Name Street')
        ->click('[data-flux-button][type="submit"]')
        ->wait(1)
        ->assertNoJavaScriptErrors();

    expect(Venue::query()->count())->toBe(0);
});
