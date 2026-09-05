<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;

/**
 * `start_tzid` on the published kind 31923 — issue #104, bug 1.
 *
 * The `start` tag was never wrong: it is an absolute Unix timestamp and 19:00 EDT is
 * 23:00 UTC either way. What was wrong is the zone identifier a client renders the wall
 * clock from, and with `Europe/Berlin` on it the reporter's Indianapolis event showed up
 * on the next day at 01:00.
 */
function tzidMeetupEvent(string $countryCode, float $cityLatitude, float $cityLongitude, array $eventAttributes = []): MeetupEvent
{
    $country = Country::factory()->create(['code' => $countryCode]);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'latitude' => $cityLatitude,
        'longitude' => $cityLongitude,
    ]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id]);

    return MeetupEvent::factory()->create(array_merge([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'osm_lat' => null,
        'osm_lon' => null,
    ], $eventAttributes));
}

function tzidTagOf(MeetupEvent $meetupEvent): ?string
{
    $tags = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, str_repeat('a1', 32))->getTag('start_tzid');

    return $tags === [] ? null : $tags[0][1];
}

it('publishes the reporter Indianapolis meetup with its own zone, not Europe/Berlin', function () {
    // The city record behind portal.bitcoindiana.org/us/meetup/indy-bitcoin-meetup.
    $meetupEvent = tzidMeetupEvent('us', 39.7684, -86.1581);

    expect(tzidTagOf($meetupEvent))->toBe('America/Indiana/Indianapolis');
});

it('resolves the other zones of a country that spans several', function (float $latitude, float $longitude, string $expected) {
    expect(tzidTagOf(tzidMeetupEvent('us', $latitude, $longitude)))->toBe($expected);
})->with([
    'New York' => [40.7128, -74.0060, 'America/New_York'],
    'Los Angeles' => [34.0522, -118.2437, 'America/Los_Angeles'],
]);

it('corrects the Austrian and Swiss identifiers the country map got wrong', function (string $code, float $latitude, float $longitude, string $expected) {
    expect(tzidTagOf(tzidMeetupEvent($code, $latitude, $longitude)))->toBe($expected);
})->with([
    'Wien was Europe/Berlin' => ['at', 48.2082, 16.3738, 'Europe/Vienna'],
    'Zürich was Europe/Berlin' => ['ch', 47.3769, 8.5417, 'Europe/Zurich'],
]);

/**
 * A time zone boundary can run between a city's centre and the venue — the Indiana case
 * from the issue, where neighbouring counties sit in different zones. The event's own
 * Nominatim match is therefore preferred where it exists.
 */
it('prefers the event own OSM coordinates over the meetup city for the zone', function () {
    $meetupEvent = tzidMeetupEvent('us', 40.7128, -74.0060, [
        'osm_lat' => 34.0522,
        'osm_lon' => -118.2437,
    ]);

    expect(tzidTagOf($meetupEvent))->toBe('America/Los_Angeles');
});

it('falls back to the meetup city when the event has no OSM match', function () {
    $meetupEvent = tzidMeetupEvent('us', 34.0522, -118.2437, ['osm_lat' => null, 'osm_lon' => null]);

    expect(tzidTagOf($meetupEvent))->toBe('America/Los_Angeles');
});

/**
 * Half a coordinate is not a location. Taking the half that exists and letting the
 * other default to zero names a point in the Atlantic or on the equator, and the
 * expectations below are chosen so that doing so is VISIBLE: with the missing half
 * silently read as 0.0 the first case resolves to America/New_York and the second to
 * America/Phoenix instead.
 */
it('ignores a half-filled OSM match rather than mixing it with the city coordinate', function (float $cityLatitude, float $cityLongitude, ?float $osmLatitude, ?float $osmLongitude, string $expected) {
    $meetupEvent = tzidMeetupEvent('us', $cityLatitude, $cityLongitude, [
        'osm_lat' => $osmLatitude,
        'osm_lon' => $osmLongitude,
    ]);

    expect(tzidTagOf($meetupEvent))->toBe($expected);
})->with([
    'latitude only' => [34.0522, -118.2437, 40.7128, null, 'America/Los_Angeles'],
    'longitude only' => [40.7128, -74.0060, null, -118.2437, 'America/New_York'],
]);

/**
 * The fallback decision. NIP-52 lists `start_tzid` as optional, so a missing tag is
 * spec-conformant and leaves the client rendering the absolute `start` in the reader's
 * own zone. A plausible default is what caused this issue: Europe/Berlin on an
 * Indianapolis meetup is indistinguishable from a correct answer.
 */
it('omits start_tzid entirely when the country is unknown to tzdata', function () {
    $meetupEvent = tzidMeetupEvent('zz', 39.7684, -86.1581);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, str_repeat('a1', 32));

    expect($event->getTag('start_tzid'))->toBe([])
        // The absolute instant is untouched — this is the half that was always right.
        ->and($event->getTag('start'))->toBe([['start', (string) $meetupEvent->start->getTimestamp()]]);
});

/**
 * A malformed `countries.code` is not a hypothetical: with PER_COUNTRY,
 * DateTimeZone::listIdentifiers() throws a ValueError for anything that is not two
 * letters, so before the guard an empty or three-letter code took the entire publish
 * run down with an uncaught error instead of dropping one optional tag.
 *
 * (`meetups.city_id` is NOT NULL, so the missing-city path this used to test cannot be
 * reached through the database at all; a bad country code can.)
 */
it('drops the tag instead of throwing when the stored country code is malformed', function (string $code) {
    $meetupEvent = tzidMeetupEvent('de', 52.5200, 13.4050);
    $meetupEvent->meetup->city->country->update(['code' => $code]);
    $meetupEvent->load('meetup.city.country');

    expect(NostrCalendarEventFactory::forMeetupEvent($meetupEvent, str_repeat('a1', 32))->getTag('start_tzid'))
        ->toBe([]);
})->with([
    'empty' => [''],
    'three letters' => ['DEU'],
]);
