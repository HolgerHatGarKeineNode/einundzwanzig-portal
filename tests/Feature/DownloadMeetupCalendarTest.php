<?php

use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use function Pest\Laravel\get;

it('redirects when meetup parameter contains invalid characters', function () {
    $response = get('/stream-calendar?meetup=49)');

    $response->assertRedirect();
});

it('redirects when meetup parameter is not an integer', function () {
    $response = get('/stream-calendar?meetup=abc');

    $response->assertRedirect();
});

it('returns 404 when meetup ID does not exist', function () {
    $response = get('/stream-calendar?meetup=999999');

    $response->assertStatus(404);
});

it('returns calendar for valid meetup ID', function () {
    $country = Country::factory()->create();
    $city = \App\Models\City::factory()->create([
        'country_id' => $country->id,
    ]);
    $meetup = Meetup::factory()->create([
        'city_id' => $city->id,
    ]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay(),
    ]);

    $response = get("/stream-calendar?meetup={$meetup->id}");

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
});

it('returns 429 when rate limit is exceeded', function () {
    $country = Country::factory()->create();
    $city = \App\Models\City::factory()->create([
        'country_id' => $country->id,
    ]);
    $meetup = Meetup::factory()->create([
        'city_id' => $city->id,
    ]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay(),
    ]);

    // Make 61 requests to exceed the 60 per minute limit
    for ($i = 0; $i < 61; $i++) {
        $response = get("/stream-calendar?meetup={$meetup->id}");
    }

    // The last request should be rate limited
    $response->assertStatus(429);
});
