<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;

beforeEach(function () {
    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create(['country_id' => $country->id]);
});

it('returns visible meetups in JSON shape on GET /api/meetups', function () {
    Meetup::factory()->create([
        'city_id' => $this->city->id,
        'visible_on_map' => true,
        'name' => 'Visible Meetup',
        'community' => 'einundzwanzig',
    ]);
    Meetup::factory()->create([
        'city_id' => $this->city->id,
        'visible_on_map' => false,
        'name' => 'Hidden Meetup',
    ]);

    $response = $this->getJson('/api/meetups');

    $response->assertSuccessful();
    $names = collect($response->json())->pluck('name');
    expect($names->all())->toContain('Visible Meetup')
        ->not->toContain('Hidden Meetup');
});

it('includes intro and logo when ?withIntro=1&withLogos=1 is provided', function () {
    Meetup::factory()->create([
        'city_id' => $this->city->id,
        'visible_on_map' => true,
        'name' => 'WithExtras',
        'intro' => 'Some intro text',
    ]);

    $response = $this->getJson('/api/meetups?withIntro=1&withLogos=1');

    $response->assertSuccessful();
    $payload = collect($response->json())->firstWhere('name', 'WithExtras');
    expect($payload)
        ->intro->toBe('Some intro text')
        ->logo->toBeString();
});

it('returns einundzwanzig community meetups in BTC-Map format', function () {
    Meetup::factory()->create([
        'city_id' => $this->city->id,
        'community' => 'einundzwanzig',
        'name' => 'BTC Map Meetup',
    ]);
    Meetup::factory()->create([
        'city_id' => $this->city->id,
        'community' => 'other',
        'name' => 'Excluded Meetup',
    ]);

    $response = $this->getJson('/api/btc-map-communities');

    $response->assertSuccessful()
        ->assertJsonStructure([['id', 'tags' => ['type', 'name']]]);

    $names = collect($response->json())->pluck('tags.name');
    expect($names->all())
        ->toContain('BTC Map Meetup')
        ->not->toContain('Excluded Meetup');
});

it('returns meetup events as JSON on GET /api/meetup-events', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addDay(),
    ]);

    $response = $this->getJson('/api/meetup-events');

    $response->assertSuccessful()
        // id + Zähler werden für das RSVP im mobilen Slide-In gebraucht.
        ->assertJsonStructure([['id', 'start', 'attendees', 'might_attendees', 'meetup.name']]);
    expect($response->json())->toBeArray()->not->toBeEmpty();
});

it('filters /api/meetup-events by date when one is supplied', function () {
    $meetup = Meetup::factory()->create(['city_id' => $this->city->id]);
    MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addMonth()->startOfMonth()->addDays(5)]);

    $date = now()->addMonth()->startOfMonth()->format('Y-m-d');
    $response = $this->getJson("/api/meetup-events/{$date}");

    $response->assertSuccessful();
    expect($response->json())->toBeArray()->not->toBeEmpty();
});

it('returns 400 instead of 500 when the date path segment is not parseable', function () {
    $this->getJson('/api/meetup-events/'.urlencode('{date}'))
        ->assertStatus(400);

    $this->getJson('/api/meetup-events/not-a-date')
        ->assertStatus(400);
});
