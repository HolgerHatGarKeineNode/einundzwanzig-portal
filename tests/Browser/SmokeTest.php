<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Lecturer;
use App\Models\Meetup;
use App\Models\SelfHostedService;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create(['country_id' => $country->id]);
    Meetup::factory()->create(['city_id' => $city->id, 'visible_on_map' => true]);
    Lecturer::factory()->create();
    SelfHostedService::factory()->create();
});

it('loads all listed public pages without console errors or JS errors', function () {
    $pages = visit([
        '/welcome',
        '/login',
        '/forgot-password',
        '/de/meetups',
        '/de/courses',
        '/de/lecturers',
        '/de/cities',
        '/de/services',
    ]);

    $pages->assertNoSmoke();
});
