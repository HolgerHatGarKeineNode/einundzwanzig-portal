<?php

use App\Support\NostrCalendarAddress;

/*
|--------------------------------------------------------------------------
| NostrCalendarAddress (Issue #49)
|--------------------------------------------------------------------------
|
| The naddr strings asserted below are not this implementation's own output
| recorded as a baseline — that would only prove the code keeps agreeing with
| itself. They were produced by `nak encode naddr` (fiatjaf's Go CLI, an
| independent NIP-19 implementation) and pasted in verbatim:
|
|   nak encode naddr -d meetup-359 -a <pubkey> -k 31924
|   nak encode naddr -d meetup-359 -a <pubkey> -k 31924 -r wss://nos.lol -r wss://relay.damus.io
|   nak encode naddr -d meetup-event-1234 -a <pubkey> -k 31923 -r wss://nos.lol -r wss://relay.damus.io
|
| The pubkey is a throwaway generated for this test and used nowhere else.
*/

const ADDRESS_TEST_PUBKEY = '45df061ee03c855bdd2c3ecea528d5725e3331e465cd38f14bfe403422952a03';

const RELAYS = ['wss://nos.lol', 'wss://relay.damus.io'];

it('encodes a calendar coordinate to the same naddr as nak, without relay hints', function () {
    $address = NostrCalendarAddress::fromCoordinate('31924:'.ADDRESS_TEST_PUBKEY.':meetup-359', []);

    expect($address->naddr())->toBe(
        'naddr1qq9x6et9w36hqtfnx5usygz9murpacpus4da6tp7e6jj34tjtcenrer9e5u0zjl7gq6z99f2qvpsgqqq0j6qp5rg08'
    );
});

it('encodes a calendar coordinate to the same naddr as nak, with relay hints', function () {
    $address = NostrCalendarAddress::fromCoordinate('31924:'.ADDRESS_TEST_PUBKEY.':meetup-359', RELAYS);

    expect($address->naddr())->toBe(
        'naddr1qq9x6et9w36hqtfnx5uszrthwden5te0dehhxtnvdakqz9rhwden5te0wfjkccte9ejxzmt4wvhxjmczypza7ps7uq7g2k7a9slvaffg64e9uve3u3ju6w83f0lyqdpzj54qxqcyqqq8edq9gndqg'
    );
});

it('encodes a time-based event coordinate to the same naddr as nak', function () {
    $address = NostrCalendarAddress::fromCoordinate('31923:'.ADDRESS_TEST_PUBKEY.':meetup-event-1234', RELAYS);

    expect($address->naddr())->toBe(
        'naddr1qqgk6et9w36hqtt9wejkuapdxyerxdqpp4mhxue69uhkummn9ekx7mqpz3mhxue69uhhyetvv9ujuerpd46hxtnfdupzq3wlqc0wq0y9t0wjc0kw555d2uj7xvc7gewd8rc5hljqxs3f22srqvzqqqrukv3rmguj'
    );
});

it('parses the three parts of a coordinate', function () {
    $address = NostrCalendarAddress::fromCoordinate('31923:'.ADDRESS_TEST_PUBKEY.':meetup-event-7', RELAYS);

    expect($address->kind)->toBe(31923)
        ->and($address->pubkeyHex)->toBe(ADDRESS_TEST_PUBKEY)
        ->and($address->dTag)->toBe('meetup-event-7')
        ->and($address->isCalendar())->toBeFalse();
});

it('returns null for anything that is not a usable coordinate', function (?string $coordinate) {
    expect(NostrCalendarAddress::fromCoordinate($coordinate, RELAYS))->toBeNull();
})->with([
    'null' => null,
    'empty' => '',
    'whitespace only' => '   ',
    'no separators' => 'meetup-359',
    'two parts only' => '31924:'.ADDRESS_TEST_PUBKEY,
    'pubkey too short' => '31924:abc:meetup-359',
    'pubkey not hex' => '31924:zzzz61ee03c855bdd2c3ecea528d5725e3331e465cd38f14bfe403422952a03:meetup-359',
    'uppercase pubkey' => '31924:'.strtoupper(ADDRESS_TEST_PUBKEY).':meetup-359',
    'kind not numeric' => 'note:'.ADDRESS_TEST_PUBKEY.':meetup-359',
    'empty d tag' => '31924:'.ADDRESS_TEST_PUBKEY.':',
]);

it('keeps colons inside the d tag', function () {
    // NIP-01 puts no restriction on the `d` value, so the split must be bounded at three
    // parts. This portal's own d tags contain no colon, but a coordinate copied in from
    // elsewhere may, and silently truncating it would produce a valid-looking wrong link.
    $address = NostrCalendarAddress::fromCoordinate('31924:'.ADDRESS_TEST_PUBKEY.':a:b:c', RELAYS);

    expect($address->dTag)->toBe('a:b:c');
});

/*
 * Which viewer is offered for which kind is a measured claim, so it is pinned here.
 * letsmiti.app rendered "Event Not Found" for a real kind 31924 on /event/ and needed
 * /calendar/; plektos.app rendered a real 31924 that carries 22 `a` tags without
 * listing a single member event, as a "party" with "No time specified", which is why
 * it is offered for events only.
 *
 * The earlier version of this comment said plektos dropped the calendar's description.
 * That was wrong — the sampled calendar simply had empty content. See the correction
 * in NostrCalendarAddress for what fooled the measurement.
 */
it('offers only viewers that render a calendar, on their calendar route', function () {
    $address = NostrCalendarAddress::fromCoordinate('31924:'.ADDRESS_TEST_PUBKEY.':meetup-359', RELAYS);

    $labels = array_column($address->viewers(), 'label');
    $urls = array_column($address->viewers(), 'url');

    expect($labels)->toBe(['mynostr.app', 'letsmiti.app', 'njump.me'])
        ->and($labels)->not->toContain('plektos.app');

    foreach ($urls as $url) {
        expect($url)->toContain($address->naddr());
    }

    expect(collect($urls)->first(fn ($u) => str_contains($u, 'letsmiti')))
        ->toContain('/calendar/')
        ->not->toContain('/event/');
});

it('offers the event viewers on their event route', function () {
    $address = NostrCalendarAddress::fromCoordinate('31923:'.ADDRESS_TEST_PUBKEY.':meetup-event-1', RELAYS);

    $labels = array_column($address->viewers(), 'label');
    $urls = array_column($address->viewers(), 'url');

    expect($labels)->toBe(['mynostr.app', 'plektos.app', 'letsmiti.app', 'njump.me']);

    expect(collect($urls)->first(fn ($u) => str_contains($u, 'letsmiti')))
        ->toContain('/event/')
        ->not->toContain('/calendar/');
});

it('reports the relays the address was built with', function () {
    $address = NostrCalendarAddress::fromCoordinate('31924:'.ADDRESS_TEST_PUBKEY.':meetup-359', RELAYS);

    expect($address->relays())->toBe(RELAYS);
});

// The config() fallback needs a booted application, so it lives in the feature test
// (tests/Feature/Meetups/NostrCalendarAddressDisplayTest.php) rather than here.
