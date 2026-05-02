<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\SelfHostedService;
use App\Models\Venue;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    Venue::factory()->create(['city_id' => $city->id]);
    Meetup::factory()->create(['city_id' => $city->id]);
    Lecturer::factory()->create();
    SelfHostedService::factory()->create();
});

it('returns successful response for authenticated routes', function (string $path) {
    actingAsUser();
    $this->get($path)->assertSuccessful();
})->with([
    'meetup create' => '/de/meetup-create',
    'course create' => '/de/course-create',
    'lecturer create' => '/de/lecturer-create',
    'city create' => '/de/city-create',
    'venue create' => '/de/venue-create',
    'service create' => '/de/service-create',
    'settings profile' => '/de/settings/profile',
    'settings password' => '/de/settings/password',
    'settings appearance' => '/de/settings/appearance',
    'verify email notice' => '/verify-email',
    'confirm password' => '/confirm-password',
    'dashboard' => '/de/dashboard',
]);

it('redirects to login when guest accesses protected routes', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with([
    'meetup create' => '/de/meetup-create',
    'service create' => '/de/service-create',
    'settings profile' => '/de/settings/profile',
]);

it('redirects /de/settings to /settings/profile (current behaviour drops the country prefix)', function () {
    actingAsUser();
    $this->get('/de/settings')->assertRedirect('/settings/profile');
});
