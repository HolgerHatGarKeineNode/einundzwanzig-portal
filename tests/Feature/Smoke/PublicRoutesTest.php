<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\SelfHostedService;
use App\Models\Venue;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    Venue::factory()->create(['city_id' => $city->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id]);
    MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);
    Course::factory()->create();
    Lecturer::factory()->create();
    SelfHostedService::factory()->create();
});

it('returns a successful response for the listed public route', function (string $path) {
    $this->get($path)->assertSuccessful();
})->with([
    'welcome' => '/welcome',
    'login' => '/login',
    'register' => '/register',
    'forgot password' => '/forgot-password',
    'meetups index' => '/de/meetups',
    'meetups all' => '/de/all-meetups',
    'map' => '/de/map',
    'map world' => '/de/map-world',
    'courses index' => '/de/courses',
    'lecturers index' => '/de/lecturers',
    'cities index' => '/de/cities',
    'venues index' => '/de/venues',
    'services index' => '/de/services',
]);

it('redirects / to /welcome', function () {
    $this->get('/')->assertRedirect('/welcome');
});

it('redirects /de/dashboard to login when guest', function () {
    $this->get('/de/dashboard')->assertRedirect(route('login'));
});

it('renders /kaninchenbau as a Livewire helper page', function () {
    $response = $this->get('/kaninchenbau');
    expect($response->status())->toBeIn([200, 302]);
});

it('returns 404 for the application fallback route', function () {
    $this->get('/this-route-does-not-exist')->assertNotFound();
});

it('aborts with the requested status code for /error/{code}', function () {
    $this->get('/error/418')->assertStatus(418);
});
