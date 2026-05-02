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

it('aborts with the requested status code for /error/{code}', function (int $code) {
    $this->get('/error/'.$code)->assertStatus($code);
})->with([
    'teapot' => 418,
    'forbidden' => 403,
    'not found' => 404,
    'server error' => 500,
]);

it('returns 404 for /error/{code} when the code is not three digits', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'non-numeric' => '/error/error.log',
    'letters' => '/error/abc',
    'two digits' => '/error/42',
    'four digits' => '/error/1000',
    'mixed' => '/error/4xx',
    'path traversal' => '/error/..%2Fetc%2Fpasswd',
]);

it('returns 404 for /error/{code} when code is outside the 4xx-5xx range', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    'success' => '/error/200',
    'redirect' => '/error/301',
    'too high' => '/error/600',
]);
