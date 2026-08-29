<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\GeoHash;
use App\Support\NostrCalendarEventFactory;

function testPubkey(): string
{
    return str_repeat('a1', 32);
}

function makeMeetupWithCity(array $meetupAttrs = [], array $cityAttrs = [], array $countryAttrs = []): Meetup
{
    $country = Country::factory()->create(array_merge(['code' => 'de'], $countryAttrs));
    $city = City::factory()->create(array_merge([
        'country_id' => $country->id,
        'name' => 'Berlin',
        'latitude' => 52.5200,
        'longitude' => 13.4050,
    ], $cityAttrs));

    return Meetup::factory()->create(array_merge(['city_id' => $city->id], $meetupAttrs));
}

it('sets kind 31924 with the required d and title tags', function () {
    $meetup = makeMeetupWithCity(['name' => 'Bitcoin Meetup Berlin']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect($event->getKind())->toBe(31924)
        ->and($event->getTag('d'))->toBe([['d', "meetup-{$meetup->id}"]])
        ->and($event->getTag('title'))->toBe([['title', 'Bitcoin Meetup Berlin']]);
});

it('uses the meetup intro as content', function () {
    $meetup = makeMeetupWithCity(['intro' => 'Wir treffen uns jeden Dienstag.']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect($event->getContent())->toBe('Wir treffen uns jeden Dienstag.');
});

it('builds a location tag from city and country name', function () {
    $meetup = makeMeetupWithCity(cityAttrs: ['name' => 'Berlin'], countryAttrs: ['name' => 'Deutschland']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect($event->getTag('location'))->toBe([['location', 'Berlin, Deutschland']]);
});

it('encodes the city coordinates as a 5-character geohash', function () {
    $meetup = makeMeetupWithCity(cityAttrs: ['latitude' => 52.5200, 'longitude' => 13.4050]);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect($event->getTag('g'))->toBe([['g', GeoHash::encode(52.5200, 13.4050, 5)]]);
});

it('adds r tags for every configured social link and skips absent ones', function () {
    $meetup = makeMeetupWithCity([
        'webpage' => 'https://berlin.einundzwanzig.space',
        'telegram_link' => 'https://t.me/berlin_btc',
        'twitter_username' => 'btc_berlin',
        'nostr' => 'npub1'.str_repeat('a', 58),
        'matrix_group' => null,
    ]);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect($event->getTag('r'))->toBe([
        ['r', 'https://berlin.einundzwanzig.space'],
        ['r', 'https://t.me/berlin_btc'],
        ['r', 'https://twitter.com/btc_berlin'],
        ['r', 'nostr:npub1'.str_repeat('a', 58)],
    ]);
});

it('omits the r tag for a malformed nostr handle', function () {
    $meetup = makeMeetupWithCity([
        'webpage' => null,
        'telegram_link' => null,
        'twitter_username' => null,
        'nostr' => 'not-an-npub',
    ]);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect($event->getTag('r'))->toBe([]);
});

it('sets kind 31923 with the required d, title, start and D tags', function () {
    $meetup = makeMeetupWithCity(['name' => 'Bitcoin Meetup Berlin']);
    $start = now()->addWeek()->startOfMinute();
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => null,
        'start' => $start,
        'end' => null,
    ]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, testPubkey());

    expect($event->getKind())->toBe(31923)
        ->and($event->getTag('d'))->toBe([['d', "meetup-event-{$meetupEvent->id}"]])
        // Kein eigener Titel -> der Name des Meetups gilt, wie in MeetupEventResource dokumentiert.
        ->and($event->getTag('title'))->toBe([['title', 'Bitcoin Meetup Berlin']])
        ->and($event->getTag('start'))->toBe([['start', (string) $start->getTimestamp()]])
        ->and($event->getTag('D'))->toBe([['D', (string) intdiv($start->getTimestamp(), 86400)]])
        ->and($event->getTag('end'))->toBe([]);
});

it('uses the event own title when set', function () {
    $meetup = makeMeetupWithCity(['name' => 'Bitcoin Meetup Berlin']);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'Sonderausgabe: Lightning-Workshop',
    ]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, testPubkey());

    expect($event->getTag('title'))->toBe([['title', 'Sonderausgabe: Lightning-Workshop']]);
});

it('adds an end tag only when the event has an end time', function () {
    $meetup = makeMeetupWithCity();
    $start = now()->addWeek()->startOfMinute();
    $end = $start->copy()->addHours(2);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => $start,
        'end' => $end,
    ]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, testPubkey());

    expect($event->getTag('end'))->toBe([['end', (string) $end->getTimestamp()]]);
});

it('resolves start_tzid from the meetup city country code', function () {
    $meetup = makeMeetupWithCity(countryAttrs: ['code' => 'nl']);
    $meetupEvent = MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, testPubkey());

    expect($event->getTag('start_tzid'))->toBe([['start_tzid', 'Europe/Amsterdam']]);
});

it('prefers the event own OSM coordinates for the geohash over the city coordinates', function () {
    $meetup = makeMeetupWithCity(cityAttrs: ['latitude' => 52.5200, 'longitude' => 13.4050]);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'osm_lat' => 48.2082,
        'osm_lon' => 16.3738,
    ]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, testPubkey());

    expect($event->getTag('g'))->toBe([['g', GeoHash::encode(48.2082, 16.3738, 5)]]);
});

it('falls back to the meetup city coordinates when the event has no OSM match', function () {
    $meetup = makeMeetupWithCity(cityAttrs: ['latitude' => 52.5200, 'longitude' => 13.4050]);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'osm_lat' => null,
        'osm_lon' => null,
    ]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, testPubkey());

    expect($event->getTag('g'))->toBe([['g', GeoHash::encode(52.5200, 13.4050, 5)]]);
});

it('links back to its calendar via the a tag using the given pubkey', function () {
    $meetup = makeMeetupWithCity();
    $meetupEvent = MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, testPubkey());

    expect($event->getTag('a'))->toBe([
        ['a', '31924:'.testPubkey().":meetup-{$meetup->id}"],
    ]);
});

it('builds coordinates in the <kind>:<pubkey>:<d-tag> format', function () {
    expect(NostrCalendarEventFactory::coordinate(31924, testPubkey(), 'meetup-42'))
        ->toBe('31924:'.testPubkey().':meetup-42');
});
