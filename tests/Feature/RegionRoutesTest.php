<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\Region;

beforeEach(function () {
    $this->us = Country::factory()->create(['code' => 'us']);
    $this->indiana = Region::factory()->indiana()->create(['country_id' => $this->us->id]);

    $this->indianapolis = City::factory()->create([
        'country_id' => $this->us->id,
        'region_id' => $this->indiana->id,
        'name' => 'Indianapolis, IN',
    ]);

    // Stadt im selben Land, aber ohne Region — darf auf der Regionsseite nicht auftauchen.
    $this->austin = City::factory()->create([
        'country_id' => $this->us->id,
        'region_id' => null,
        'name' => 'Austin, TX',
    ]);

    $this->indyMeetup = Meetup::factory()->create([
        'city_id' => $this->indianapolis->id,
        'name' => 'Indy Bitcoin Meetup',
    ]);

    $this->austinMeetup = Meetup::factory()->create([
        'city_id' => $this->austin->id,
        'name' => 'Austin Bitcoin Meetup',
    ]);
});

it('filters meetups down to the region', function () {
    $this->get('/us/in/meetups')
        ->assertSuccessful()
        ->assertSee('Indy Bitcoin Meetup')
        ->assertDontSee('Austin Bitcoin Meetup');
});

it('leaves the country route showing everything in the country', function () {
    // Die Regressionsprobe: /us/meetups darf sich durch die Regionsroute nicht aendern.
    $this->get('/us/meetups')
        ->assertSuccessful()
        ->assertSee('Indy Bitcoin Meetup')
        ->assertSee('Austin Bitcoin Meetup');
});

it('filters the map and the city list too', function () {
    $this->get('/us/in/map')->assertSuccessful();
    $this->get('/us/in/cities')
        ->assertSuccessful()
        ->assertSee('Indianapolis, IN')
        ->assertDontSee('Austin, TX');
});

it('returns 404 for a region the country does not have', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'meetups' => '/us/zz/meetups',
    'map' => '/us/zz/map',
    'cities' => '/us/zz/cities',
]);

it('does not shadow the existing detail routes', function () {
    // /{country}/{region}/meetups hat dieselbe Segmentzahl wie /{country}/meetup/{slug}.
    // Das Constraint [a-z]{2} muss verhindern, dass "meetup" als Region gelesen wird.
    $this->get("/us/meetup/{$this->indyMeetup->slug}")->assertSuccessful();
});

it('keeps the country filter out of the all-meetups route', function () {
    $de = Country::factory()->create(['code' => 'de']);
    $berlin = City::factory()->create(['country_id' => $de->id, 'name' => 'Berlin Test']);
    Meetup::factory()->create(['city_id' => $berlin->id, 'name' => 'Berlin Bitcoin Meetup']);

    $this->get('/us/all-meetups')
        ->assertSuccessful()
        ->assertSee('Berlin Bitcoin Meetup')
        ->assertSee('Indy Bitcoin Meetup');
});

it('shows an empty-state pointing back to the country when the region has no meetups', function () {
    $ohio = Region::factory()->create([
        'country_id' => $this->us->id,
        'code' => 'oh',
        'name' => 'Ohio',
        'slug' => 'ohio',
    ]);

    $this->get('/us/oh/meetups')
        ->assertSuccessful()
        ->assertSee('Ohio')
        ->assertDontSee('Indy Bitcoin Meetup');
});
