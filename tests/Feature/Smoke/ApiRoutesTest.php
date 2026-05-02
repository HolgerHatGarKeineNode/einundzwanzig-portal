<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Highscore;
use App\Models\Lecturer;
use App\Models\LibraryItem;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use App\Models\Venue;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    Venue::factory()->create(['city_id' => $city->id]);
    Meetup::factory()->create(['city_id' => $city->id, 'community' => 'einundzwanzig', 'visible_on_map' => true]);
    MeetupEvent::factory()->create();
    Course::factory()->create();
    CourseEvent::factory()->create();
    Lecturer::factory()->create();
    Highscore::factory()->create();
    LibraryItem::factory()->create(['type' => 'bindle']);
    User::factory()->create(['nostr' => 'npub1'.str_repeat('a', 58)]);
});

it('returns a JSON response for the API GET endpoint', function (string $path) {
    $this->getJson($path)->assertSuccessful();
})->with([
    'countries' => '/api/countries',
    'meetups' => '/api/meetups',
    'meetup events' => '/api/meetup-events',
    'btc-map communities' => '/api/btc-map-communities',
    'nostrplebs' => '/api/nostrplebs',
    'bindles' => '/api/bindles',
    'lecturers' => '/api/lecturers',
    'courses' => '/api/courses',
    'cities' => '/api/cities',
    'venues' => '/api/venues',
    'highscores' => '/api/highscores',
]);

it('returns 404 for /api/meetup/ical (currently a stub that aborts)', function () {
    $this->get('/api/meetup/ical')->assertNotFound();
});

it('returns 404 for /api/meetup index without user_id (currently aborts on missing param)', function () {
    $this->getJson('/api/meetup')->assertNotFound();
});

it('returns a successful response for /stream-calendar', function () {
    $response = $this->get('/stream-calendar');
    $response->assertSuccessful();
});
