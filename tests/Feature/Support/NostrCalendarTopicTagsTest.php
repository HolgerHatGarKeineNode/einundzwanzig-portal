<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\Region;
use App\Support\GeoHash;
use App\Support\NostrCalendarEventFactory;
use swentel\nostr\Event\Event;

/*
 * Issue #69: published NIP-52 events carried a `g` geohash and nothing searchable.
 * These tests pin the `t` topic tags derived from the meetup's address — the full
 * set, every partial address, the normalisation, and the fact that the geohash is
 * untouched.
 *
 * Helper names are prefixed `topic*` on purpose: Pest loads every test file into the
 * same process, and `NostrCalendarEventFactoryTest.php` next door already owns the
 * global names `testPubkey()` and `makeMeetupWithCity()`.
 */

function topicPubkey(): string
{
    return str_repeat('b2', 32);
}

/**
 * The values of the `t` tags, in the order the factory emitted them.
 *
 * @return list<string>
 */
function topicValues(Event $event): array
{
    return array_map(static fn (array $tag): string => $tag[1], $event->getTag('t'));
}

/**
 * A meetup in a city with the given address parts.
 *
 * `$region` is either null (city without a region, the shape of 300 of the 305 stored
 * cities) or `['code' => …, 'name' => …]`.
 *
 * @param  array{code: string, name: string}|null  $region
 */
function topicMeetup(string $cityName, string $countryCode = 'us', ?array $region = null): Meetup
{
    $country = Country::factory()->create(['code' => $countryCode]);

    $regionModel = $region === null ? null : Region::factory()->create([
        'country_id' => $country->id,
        'code' => $region['code'],
        'name' => $region['name'],
    ]);

    $city = City::factory()->create([
        'country_id' => $country->id,
        'region_id' => $regionModel?->id,
        'name' => $cityName,
        'latitude' => 39.7684,
        'longitude' => -86.1581,
    ]);

    return Meetup::factory()->create(['city_id' => $city->id]);
}

it('derives the full topic set from a complete address', function () {
    $meetup = topicMeetup('Indianapolis', 'us', ['code' => 'in', 'name' => 'Indiana']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    // The set from issue #69, in the order the reporter listed it: the two static
    // topics first, then the geography from most to least specific.
    expect(topicValues($event))->toBe([
        'bitcoin', 'meetup', 'indianapolis', 'indiana', 'in-us', 'in', 'us',
    ]);
});

it('carries the same topic tags on the time-based calendar event', function () {
    $meetup = topicMeetup('Indianapolis', 'us', ['code' => 'in', 'name' => 'Indiana']);
    $meetupEvent = MeetupEvent::factory()->create(['meetup_id' => $meetup->id]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, topicPubkey());

    expect($event->getKind())->toBe(31923)
        ->and(topicValues($event))->toBe([
            'bitcoin', 'meetup', 'indianapolis', 'indiana', 'in-us', 'in', 'us',
        ]);
});

it('lowercases stored codes that are held in upper case', function () {
    // CountryFactory writes uppercase codes into every development and test database
    // (issue #76), so this is the ordinary shape here, not a contrived one. NIP-24:
    // "`t`: a hashtag. The value MUST be a lowercase string."
    $meetup = topicMeetup('Indianapolis', 'US', ['code' => 'IN', 'name' => 'Indiana']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect(topicValues($event))->toBe([
        'bitcoin', 'meetup', 'indianapolis', 'indiana', 'in-us', 'in', 'us',
    ]);
});

it('omits the region topics when the city has no region', function () {
    $meetup = topicMeetup('Indianapolis', 'us');

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect(topicValues($event))->toBe(['bitcoin', 'meetup', 'indianapolis', 'us']);
});

it('omits the code topics when the region carries no code', function () {
    $meetup = topicMeetup('Indianapolis', 'us', ['code' => '', 'name' => 'Indiana']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    // The region NAME still stands on its own; only `in-us` and `in` need the code.
    expect(topicValues($event))->toBe(['bitcoin', 'meetup', 'indianapolis', 'indiana', 'us']);
});

it('omits the country topics when the city has no country', function () {
    $meetup = topicMeetup('Indianapolis', 'us', ['code' => 'in', 'name' => 'Indiana']);

    // `cities.country_id` is NOT NULL, so this state cannot be stored — but the factory
    // reads the relation through `?->` and would emit `['t', '']` for a detached one.
    // Detaching the loaded relation is the only way to exercise that guard.
    $meetup->city->setRelation('country', null);
    $meetup->setRelation('city', $meetup->city);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    // `in-us` needs both halves and falls away; the bare region code does not.
    expect(topicValues($event))->toBe(['bitcoin', 'meetup', 'indianapolis', 'indiana', 'in']);
});

it('emits the static topics and the city alone when nothing else is known', function () {
    $meetup = topicMeetup('Indianapolis', '');

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect(topicValues($event))->toBe(['bitcoin', 'meetup', 'indianapolis']);
});

it('emits only the static topics when the meetup has no city at all', function () {
    $meetup = topicMeetup('Indianapolis');
    $meetup->setRelation('city', null);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect(topicValues($event))->toBe(['bitcoin', 'meetup']);
});

it('emits no empty topic for address parts that normalise to nothing', function () {
    $meetup = topicMeetup('Indianapolis', '  ', ['code' => ' ', 'name' => '--']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect(topicValues($event))->toBe(['bitcoin', 'meetup', 'indianapolis'])
        ->and(topicValues($event))->not->toContain('');
});

it('hyphenates multi-word and punctuated names', function () {
    $meetup = topicMeetup('St. Gallen', 'ch', ['code' => 'sg', 'name' => 'Sankt Gallen']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect(topicValues($event))->toBe([
        'bitcoin', 'meetup', 'st-gallen', 'sankt-gallen', 'sg-ch', 'sg', 'ch',
    ]);
});

it('adds an ASCII fold next to a German name', function () {
    $meetup = topicMeetup('München', 'de', ['code' => 'by', 'name' => 'Bayern']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect(topicValues($event))->toBe([
        'bitcoin', 'meetup', 'münchen', 'munchen', 'bayern', 'by-de', 'by', 'de',
    ]);
});

it('adds an ASCII fold for Polish, Hungarian, Czech and Latvian names', function () {
    $cases = [
        ['Łódź', 'pl', ['łódź', 'lodz', 'pl']],
        ['Székesfehérvár', 'hu', ['székesfehérvár', 'szekesfehervar', 'hu']],
        ['Plzeň', 'cz', ['plzeň', 'plzen', 'cz']],
        ['Rīga', 'lv', ['rīga', 'riga', 'lv']],
    ];

    foreach ($cases as [$cityName, $countryCode, $expected]) {
        $event = NostrCalendarEventFactory::forMeetup(topicMeetup($cityName, $countryCode));

        expect(topicValues($event))->toBe(array_merge(['bitcoin', 'meetup'], $expected));
    }
});

it('keeps a combining mark instead of breaking the word at it', function () {
    // A decomposed "München": "Mu" + U+0308 (combining diaeresis) + "nchen". Rare, but
    // the one input shape where a Unicode-aware regex silently produces nonsense — with
    // `\p{M}` missing from the keep set the mark becomes a separator and the tag reads
    // `mu-nchen`. Note the native tag is NOT byte-identical to the precomposed `münchen`
    // (that is what NFC would fix, and this codebase does not normalise). Precisely why
    // the ASCII fold earns its place: `munchen` is the same string for both shapes.
    $meetup = topicMeetup("Mu\u{0308}nchen", 'de');

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect(topicValues($event))->toBe(['bitcoin', 'meetup', "mu\u{0308}nchen", 'munchen', 'de']);
});

it('emits each topic once when two address parts normalise to the same value', function () {
    $meetup = topicMeetup('New York', 'us', ['code' => 'ny', 'name' => 'New York']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    // City and region both yield `new-york`; a repeated `t` tag is legal but useless.
    expect(topicValues($event))->toBe([
        'bitcoin', 'meetup', 'new-york', 'ny-us', 'ny', 'us',
    ]);
});

it('leaves the geohash tag untouched', function () {
    $meetup = topicMeetup('Indianapolis', 'us', ['code' => 'in', 'name' => 'Indiana']);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect($event->getTag('g'))->toBe([['g', GeoHash::encode(39.7684, -86.1581, 5)]]);
});
