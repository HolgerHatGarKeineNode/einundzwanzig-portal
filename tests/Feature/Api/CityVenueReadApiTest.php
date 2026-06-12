<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Venue;

it('limits GET /api/cities to 10 entries without withDetails', function () {
    City::factory()->count(11)->create(['country_id' => Country::factory()->create()->id]);

    $this->getJson('/api/cities')
        ->assertSuccessful()
        ->assertJsonCount(10);
});

it('returns all cities with country code and flag on GET /api/cities?withDetails', function () {
    $cities = City::factory()->count(11)->create(['country_id' => Country::factory()->create()->id]);

    $response = $this->getJson('/api/cities?withDetails')
        ->assertSuccessful()
        ->assertJsonCount(11);

    $first = collect($response->json())->firstWhere('id', $cities->first()->id);

    expect($first)
        ->toHaveKeys(['id', 'name', 'country_id', 'country', 'flag'])
        ->and($first['country'])->toHaveKeys(['id', 'name', 'code'])
        ->and($first['flag'])->toContain('vendor/blade-flags/country-'.$cities->first()->country->code.'.svg');
});

it('limits GET /api/venues to 10 entries without withDetails', function () {
    Venue::factory()->count(11)->create(['city_id' => City::factory()->create()->id]);

    $this->getJson('/api/venues')
        ->assertSuccessful()
        ->assertJsonCount(10);
});

it('returns all venues on GET /api/venues?withDetails', function () {
    $venues = Venue::factory()->count(11)->create(['city_id' => City::factory()->create()->id]);

    $response = $this->getJson('/api/venues?withDetails')
        ->assertSuccessful()
        ->assertJsonCount(11);

    $first = collect($response->json())->firstWhere('id', $venues->first()->id);

    expect($first)
        ->toHaveKeys(['id', 'name', 'city_id', 'flag', 'description', 'city'])
        ->and($first['city']['country'])->toHaveKeys(['id', 'name', 'code']);
});
