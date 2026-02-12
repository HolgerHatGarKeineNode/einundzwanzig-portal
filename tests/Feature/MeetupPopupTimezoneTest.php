<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;

it('displays event time converted to user timezone in meetup popup', function () {
    config(['app.user-timezone' => 'Europe/Berlin']);

    $country = Country::factory()->create();
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'intro' => 'Test intro']);

    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => '2026-02-19 16:30:00', // UTC
        'location' => 'Test Location',
    ]);

    $meetup->load(['meetupEvents' => function ($query) {
        $query->where('start', '>=', now())->orderBy('start')->limit(1);
    }]);

    $html = view('components.meetup-popup', [
        'meetup' => $meetup,
        'url' => 'https://example.com',
        'eventUrl' => 'https://example.com/event',
    ])->render();

    // 16:30 UTC = 17:30 CET (Europe/Berlin in winter)
    expect($html)->toContain('17:30 Uhr')
        ->and($html)->not->toContain('16:30 Uhr');
});

it('displays event date converted to user timezone in meetup popup', function () {
    config(['app.user-timezone' => 'Europe/Berlin']);

    $country = Country::factory()->create();
    $city = City::factory()->create(['country_id' => $country->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id]);

    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => '2026-02-19 23:30:00', // UTC - next day in Berlin
        'location' => 'Test Location',
    ]);

    $meetup->load(['meetupEvents' => function ($query) {
        $query->where('start', '>=', now())->orderBy('start')->limit(1);
    }]);

    $html = view('components.meetup-popup', [
        'meetup' => $meetup,
        'url' => 'https://example.com',
        'eventUrl' => null,
    ])->render();

    // 23:30 UTC on Feb 19 = 00:30 CET on Feb 20 in Europe/Berlin
    expect($html)->toContain('20. Februar 2026');
});
