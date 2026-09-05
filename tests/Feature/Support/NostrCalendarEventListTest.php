<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;

/**
 * The `a` tags on the published kind 31924 — issue #104, bug 2.
 *
 * NIP-52 defines a calendar as "a collection of calendar events", and the collection is
 * the repeated `a` tag: `["a", "<31922 or 31923>:<author pubkey>:<d-identifier>"]`. The
 * portal emitted `d`, `title`, `location`, `g`, `t` and `r` and no `a` at all, so every
 * calendar it published was an empty shell — the reporter's cal-meetup-359 included.
 */
function calendarListMeetup(): Meetup
{
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'latitude' => 52.5200,
        'longitude' => 13.4050,
    ]);

    return Meetup::factory()->create(['city_id' => $city->id]);
}

function calendarListATags(Meetup $meetup): array
{
    return array_map(
        fn (array $tag): string => $tag[1],
        NostrCalendarEventFactory::forMeetup($meetup)->getTag('a'),
    );
}

it('lists a published event of the meetup', function () {
    $meetup = calendarListMeetup();
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => '31923:'.str_repeat('a1', 32).':meetup-event-1',
    ]);

    expect(calendarListATags($meetup))->toBe([$meetupEvent->nostr_coordinate]);
});

it('lists nothing when the meetup has no published events', function () {
    $meetup = calendarListMeetup();
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => null,
    ]);

    expect(calendarListATags($meetup))->toBe([]);
});

/**
 * Deterministic order matters for a replaceable event: a tag list that reshuffles
 * between runs changes the event id on every republish for no reason.
 */
it('orders the events by start, not by insertion', function () {
    $meetup = calendarListMeetup();
    $pubkey = str_repeat('a1', 32);

    $later = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeeks(4),
        'nostr_coordinate' => "31923:{$pubkey}:meetup-event-later",
    ]);
    $sooner = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => "31923:{$pubkey}:meetup-event-sooner",
    ]);

    expect(calendarListATags($meetup))->toBe([
        $sooner->nostr_coordinate,
        $later->nostr_coordinate,
    ]);
});

it('does not list the events of another meetup', function () {
    $meetup = calendarListMeetup();
    $other = calendarListMeetup();
    $pubkey = str_repeat('a1', 32);

    MeetupEvent::factory()->create([
        'meetup_id' => $other->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => "31923:{$pubkey}:meetup-event-elsewhere",
    ]);

    expect(calendarListATags($meetup))->toBe([]);
});

/**
 * "No tag beats a wrong tag" — the rule the rest of this factory follows. A row whose
 * coordinate is not a kind 31923 address contributes nothing rather than an `a` tag
 * pointing at something that is not a calendar event.
 */
it('skips a stored coordinate that is not a kind 31923 address', function () {
    $meetup = calendarListMeetup();
    $pubkey = str_repeat('a1', 32);

    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => "31924:{$pubkey}:meetup-event-wrong-kind",
    ]);
    $valid = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeeks(2),
        'nostr_coordinate' => "31923:{$pubkey}:meetup-event-valid",
    ]);

    expect(calendarListATags($meetup))->toBe([$valid->nostr_coordinate]);
});

/**
 * The stored address is used verbatim rather than recomputed from the current
 * publisher key: the tag has to name where the event actually IS on the relays, and
 * NIP-52 spells that position "<calendar event author pubkey>".
 */
it('uses the stored coordinate verbatim, including its own pubkey', function () {
    $meetup = calendarListMeetup();
    $otherKeysCoordinate = '31923:'.str_repeat('b2', 32).':meetup-event-published-under-an-old-key';

    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => $otherKeysCoordinate,
    ]);

    expect(calendarListATags($meetup))->toBe([$otherKeysCoordinate]);
});

it('keeps the a tags out of the way of the required d and title tags', function () {
    $meetup = calendarListMeetup();
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'nostr_coordinate' => '31923:'.str_repeat('a1', 32).':meetup-event-1',
    ]);

    $event = NostrCalendarEventFactory::forMeetup($meetup);

    expect($event->getKind())->toBe(31924)
        ->and($event->getTag('d'))->toBe([['d', "meetup-{$meetup->id}"]])
        ->and($event->getTag('title'))->toBe([['title', $meetup->name]])
        // NIP-52 allows an optional relay hint in the third position; the portal emits
        // none, so the tag stays two elements wide.
        ->and($event->getTag('a')[0])->toHaveCount(2);
});
