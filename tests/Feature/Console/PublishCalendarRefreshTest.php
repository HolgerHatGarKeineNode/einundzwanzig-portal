<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrEventTransmitter;
use swentel\nostr\Key\Key;
use Tests\Fixtures\RecordingNostrTransmitter;

/**
 * Publishing a kind 31923 must update the meetup's kind 31924 calendar — issue #104,
 * bug 2, second half.
 *
 * Emitting `a` tags from the factory is not enough on its own: `nostr:publish-calendar`
 * is gated on `nostr_coordinate IS NULL` and never re-sends, so a calendar published
 * before its events would stay empty for good. Both orders are exercised here.
 */
const REFRESH_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

function refreshTransmitter(): RecordingNostrTransmitter
{
    $transmitter = new RecordingNostrTransmitter;
    app()->instance(NostrEventTransmitter::class, $transmitter);

    return $transmitter;
}

function refreshMeetup(array $attributes = []): Meetup
{
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'latitude' => 52.5200,
        'longitude' => 13.4050,
    ]);

    return Meetup::factory()->create(array_merge([
        'city_id' => $city->id,
        'nostr_publishing_enabled' => true,
    ], $attributes));
}

function refreshPubkey(): string
{
    return (new Key)->getPublicKey(REFRESH_TEST_KEY);
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => REFRESH_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);
});

it('re-sends the calendar with an a tag for the event it just published', function () {
    $transmitter = refreshTransmitter();
    $meetup = refreshMeetup(['nostr_coordinate' => '31924:'.refreshPubkey().':meetup-1']);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])->assertExitCode(0);

    $calendars = $transmitter->ofKind(31924);

    expect($transmitter->events)->toHaveCount(2)
        ->and($transmitter->events[0]->getKind())->toBe(31923)
        ->and($calendars)->toHaveCount(1)
        ->and(RecordingNostrTransmitter::tagValue($calendars[0], 'd'))->toBe("meetup-{$meetup->id}")
        ->and(RecordingNostrTransmitter::tagValues($calendars[0], 'a'))->toBe([
            NostrCalendarEventFactory::coordinate(31923, refreshPubkey(), "meetup-event-{$meetupEvent->id}"),
        ]);
});

/**
 * The other order. Nothing special happens here: the calendar has not been published
 * yet, so it is left to the Meetup arm of the same command, which builds its `a` tags
 * from the coordinate this run just stored.
 */
it('leaves an unpublished calendar to the meetup queue, which then includes the event', function () {
    $transmitter = refreshTransmitter();
    $meetup = refreshMeetup(['nostr_coordinate' => null]);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])->assertExitCode(0);

    expect($transmitter->events)->toHaveCount(1)
        ->and($transmitter->events[0]->getKind())->toBe(31923);

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])->assertExitCode(0);

    $calendars = $transmitter->ofKind(31924);

    expect($calendars)->toHaveCount(1)
        ->and(RecordingNostrTransmitter::tagValues($calendars[0], 'a'))->toBe([
            NostrCalendarEventFactory::coordinate(31923, refreshPubkey(), "meetup-event-{$meetupEvent->id}"),
        ]);
});

/**
 * The event is published and its coordinate is stored; reporting failure now would tell
 * the scheduler that completed work did not happen, and there is no way to un-publish
 * the event to make that true.
 */
it('keeps the run successful when only the calendar refresh is rejected', function () {
    $transmitter = refreshTransmitter();
    $transmitter->rejectedKinds = [31924];

    $meetup = refreshMeetup(['nostr_coordinate' => '31924:'.refreshPubkey().':meetup-1']);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])->assertExitCode(0);

    expect($meetupEvent->refresh()->nostr_coordinate)
        ->toBe(NostrCalendarEventFactory::coordinate(31923, refreshPubkey(), "meetup-event-{$meetupEvent->id}"))
        ->and($transmitter->ofKind(31924))->toHaveCount(1);
});

it('does not touch a calendar when the event itself was rejected', function () {
    $transmitter = refreshTransmitter();
    $transmitter->accepts = false;

    $meetup = refreshMeetup(['nostr_coordinate' => '31924:'.refreshPubkey().':meetup-1']);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])->assertExitCode(1);

    expect($transmitter->ofKind(31924))->toBe([]);
});

it('does not re-send a calendar when a meetup itself is published', function () {
    $transmitter = refreshTransmitter();
    refreshMeetup();

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])->assertExitCode(0);

    expect($transmitter->events)->toHaveCount(1)
        ->and($transmitter->events[0]->getKind())->toBe(31924);
});
