<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrEventTransmitter;
use App\Support\NostrPayloadFingerprint;
use Illuminate\Support\Facades\Schema;
use swentel\nostr\Event\Event;
use Tests\Fixtures\RecordingNostrTransmitter;

/**
 * Issue #141 — a cancelled meetup event must stop looking live on the relays.
 *
 * The mechanism is deliberately NOT a NIP-52 status field, because there is none: the
 * spec defines `status` only for the RSVP kind 31925 (`accepted`/`declined`/
 * `tentative`), and no client of the ten examined for this issue writes or reads a
 * cancellation on 31922/31923. The portal therefore keeps the record and MARKS it —
 * a marker in the title and the content, which every renderer shows, plus
 * `["status","canceled"]` and its NIP-32 mirror `["L","status"]` /
 * `["l","canceled","status"]` for a reader that one day looks. The reasoning in full is
 * in {@see NostrCalendarEventFactory::CANCELLED_TITLE_PREFIX}.
 *
 * Three things have to hold, and they fail in three different ways:
 *
 *  - THE MARKER REACHES AN ALREADY-PUBLISHED RECORD. It can only do that through the
 *    #92 fingerprint, so the assertion is not "the factory builds a marker" but "the
 *    hourly `--changed` run sends it". A marker the fingerprint does not see is a marker
 *    that never leaves this repository.
 *  - THE REVERSAL REACHES IT TOO (#140). Everything the cancellation added comes off,
 *    and the fingerprint lands back on the value it had before — which is the property
 *    that makes the marker derived state rather than a one-way write.
 *  - A CANCELLED EVENT THAT WAS NEVER PUBLISHED IS NOT PUBLISHED NOW. The other two are
 *    repairs of something a subscriber has already seen; this one is about not creating
 *    the defect in the first place, and it cannot be undone afterwards because the
 *    portal emits no NIP-09 deletion.
 *
 * NO RELAY IS CONTACTED. Every test binds {@see RecordingNostrTransmitter} into the
 * container, which records events instead of opening a websocket.
 */
const CANCELLATION_TEST_KEY = 'b7d41f0a2c95e8637fa0b41d5e93c72806af5d31e4b09c76d2a85f130e6b4c9d';

function cancellationTransmitter(): RecordingNostrTransmitter
{
    $transmitter = new RecordingNostrTransmitter;
    app()->instance(NostrEventTransmitter::class, $transmitter);

    return $transmitter;
}

function cancellationMeetup(): Meetup
{
    $country = Country::factory()->create(['code' => 'de']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'latitude' => 50.9787,
        'longitude' => 11.0328,
    ]);

    return Meetup::factory()->create([
        'city_id' => $city->id,
        'nostr_publishing_enabled' => true,
        'name' => 'Bitcoin Meetup Erfurt',
    ]);
}

/**
 * A meetup and one event of it, both taken through the REAL publisher, so their
 * coordinates and stored fingerprints are the ones production would have.
 *
 * @return array{Meetup, MeetupEvent}
 */
function cancellationPublished(array $eventAttributes = []): array
{
    $meetup = cancellationMeetup();

    $meetupEvent = MeetupEvent::factory()->create(array_merge([
        'meetup_id' => $meetup->id,
        'title' => 'Stammtisch',
        'description' => 'Wir treffen uns wie immer.',
        'start' => now()->addWeek()->setTime(19, 0),
        'link' => null,
        'links' => null,
    ], $eventAttributes));

    test()->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])->assertExitCode(0);
    test()->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])->assertExitCode(0);

    return [$meetup->refresh(), $meetupEvent->refresh()];
}

/**
 * The events transmitted after the given baseline — i.e. the ones this run sent.
 *
 * @return list<Event>
 */
function cancellationSince(RecordingNostrTransmitter $transmitter, int $baseline): array
{
    return array_values(array_slice($transmitter->events, $baseline));
}

/**
 * One forced `--changed` run, and what it put on the wire.
 *
 * `--sleep 0` because the pause is throttling for real relays and there are none here;
 * the 90-second jump is what a scheduled run would have between it and the publish, and
 * it keeps `created_at` strictly newer so the assertion reads like production.
 *
 * @return list<Event>
 */
function cancellationRepublish(RecordingNostrTransmitter $transmitter): array
{
    $baseline = count($transmitter->events);

    test()->travel(90)->seconds();
    test()->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    return cancellationSince($transmitter, $baseline);
}

/**
 * Every tag of an event, in order — not just the one being asserted on.
 *
 * The cancellation adds three tags and rewrites two fields; a test that only looked at
 * the tag it expects could not tell "the marker was removed" from "the whole tag list
 * was rebuilt differently", and the un-cancel case turns on exactly that difference.
 *
 * @return list<list<string>>
 */
function cancellationTagsOf(Event $event): array
{
    return array_map(fn (array $tag): array => array_values($tag), $event->getTags());
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => CANCELLATION_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);
});

/*
|--------------------------------------------------------------------------
| The marker reaches a record that is already on the relays
|--------------------------------------------------------------------------
*/

it('re-sends a published event with the cancellation marker and the status tags', function () {
    $transmitter = cancellationTransmitter();
    [$meetup, $meetupEvent] = cancellationPublished();

    $published = $transmitter->ofKind(31923)[0];
    $fingerprintBefore = $meetupEvent->fresh()->nostr_payload_hash;

    expect($published->getContent())->toBe('Wir treffen uns wie immer.')
        ->and(RecordingNostrTransmitter::tagValue($published, 'title'))->toBe('Stammtisch')
        ->and(RecordingNostrTransmitter::tagValue($published, 'status'))->toBeNull()
        ->and($fingerprintBefore)->toMatch('/^[0-9a-f]{64}$/');

    $meetupEvent->update(['cancelled_at' => now()]);

    $republished = cancellationRepublish($transmitter);

    // EXACTLY ONE record, and it is the event. The calendar lists the event by `a` tag
    // and that list does not move when the event is called off, so a mechanism that
    // re-sent the calendar too would be doing work with nothing behind it.
    expect($republished)->toHaveCount(1)
        ->and($republished[0]->getKind())->toBe(31923)
        ->and(RecordingNostrTransmitter::tagValue($republished[0], 'd'))
        ->toBe("meetup-event-{$meetupEvent->id}");

    $sent = $republished[0];

    // The half every renderer shows.
    expect(RecordingNostrTransmitter::tagValue($sent, 'title'))->toBe('CANCELLED: Stammtisch')
        ->and($sent->getContent())->toBe("CANCELLED: this event is not taking place.\n\nWir treffen uns wie immer.");

    // The machine-readable half: the readable tag, and the single-letter NIP-32 mirror
    // that a relay can actually index. `canceled` is single-l — the only spelling in the
    // NIP set — while the human marker keeps the RFC 5545 spelling the ICS feed uses.
    expect(cancellationTagsOf($sent))->toContain(['status', 'canceled'])
        ->toContain(['L', 'status'])
        ->toContain(['l', 'canceled', 'status']);

    // The load-bearing half: the payload moved, which is the ONLY reason the run above
    // sent anything at all.
    expect($meetupEvent->fresh()->nostr_payload_hash)->not->toBe($fingerprintBefore)
        ->and($meetupEvent->fresh()->nostr_payload_hash)->toBe(NostrPayloadFingerprint::of($sent))
        // And the calendar really did stay put, rather than merely not being sent.
        ->and($meetup->fresh()->nostr_payload_hash)->toBe($meetup->nostr_payload_hash);
});

/**
 * The fingerprint is the whole mechanism, stated on its own so a failure says which half
 * broke. `NostrPayloadFingerprint::of()` hashes `[kind, tags, content]`; a marker that
 * lived anywhere else — a database flag, a rendering-time decoration — would leave this
 * value untouched and `--changed` would never look at the record again.
 */
it('moves the #92 fingerprint when an event is cancelled', function () {
    cancellationTransmitter();
    [, $meetupEvent] = cancellationPublished();

    $pubkey = (new swentel\nostr\Key\Key)->getPublicKey(CANCELLATION_TEST_KEY);

    $before = NostrPayloadFingerprint::of(
        NostrCalendarEventFactory::forMeetupEvent($meetupEvent->fresh(), $pubkey)
    );

    $meetupEvent->update(['cancelled_at' => now()]);

    $after = NostrPayloadFingerprint::of(
        NostrCalendarEventFactory::forMeetupEvent($meetupEvent->fresh(), $pubkey)
    );

    expect($after)->not->toBe($before)
        // Stale against what the relays hold, which is what the scan asks.
        ->and(NostrPayloadFingerprint::isStale(
            $meetupEvent->fresh(),
            NostrCalendarEventFactory::forMeetupEvent($meetupEvent->fresh(), $pubkey)
        ))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The reversal (#140) reaches it too
|--------------------------------------------------------------------------
*/

/**
 * Cancel, then un-cancel, with the wire payload asserted at all three points.
 *
 * The tag list is compared WHOLE and the fingerprint has to land back on its original
 * value, not merely change again. Both are the same assertion from two sides: the marker
 * is derived from `cancelled_at` on every build, so removing the cancellation leaves a
 * payload byte-identical to the one published before it. A marker written into the record
 * — or one removed by a second, separate rule — would pass "it changed" and fail this.
 */
it('takes the marker, the status tag and the NIP-32 mirror back off on an un-cancel', function () {
    $transmitter = cancellationTransmitter();
    [, $meetupEvent] = cancellationPublished();

    $original = $transmitter->ofKind(31923)[0];
    $tagsBefore = cancellationTagsOf($original);
    $contentBefore = $original->getContent();
    $fingerprintBefore = $meetupEvent->fresh()->nostr_payload_hash;

    $meetupEvent->update(['cancelled_at' => now()]);
    $cancelled = cancellationRepublish($transmitter);

    expect($cancelled)->toHaveCount(1);
    $fingerprintCancelled = $meetupEvent->fresh()->nostr_payload_hash;

    $meetupEvent->update(['cancelled_at' => null]);
    $reinstated = cancellationRepublish($transmitter);

    expect($reinstated)->toHaveCount(1)
        ->and($reinstated[0]->getKind())->toBe(31923)
        ->and(RecordingNostrTransmitter::tagValue($reinstated[0], 'd'))
        ->toBe("meetup-event-{$meetupEvent->id}");

    $sent = $reinstated[0];

    // Every trace of the cancellation is gone — named one by one, because "the tag lists
    // are equal" would also pass if the marker had never been added.
    expect(RecordingNostrTransmitter::tagValue($sent, 'title'))->toBe('Stammtisch')
        ->and(RecordingNostrTransmitter::tagValue($sent, 'status'))->toBeNull()
        ->and(RecordingNostrTransmitter::tagValue($sent, 'L'))->toBeNull()
        ->and(RecordingNostrTransmitter::tagValue($sent, 'l'))->toBeNull()
        ->and($sent->getContent())->toBe($contentBefore)
        ->and($sent->getContent())->not->toContain('CANCELLED')
        ->and(cancellationTagsOf($sent))->toBe($tagsBefore);

    // And the fingerprint is back where it started, exactly.
    expect($fingerprintCancelled)->not->toBe($fingerprintBefore)
        ->and($meetupEvent->fresh()->nostr_payload_hash)->toBe($fingerprintBefore);
});

/**
 * The reinstated record must then be QUIET. `--changed` is self-terminating by design
 * (issue #92), and a cancellation that toggles the payload back and forth without
 * settling would turn one organiser's mistake into a standing re-send.
 */
it('sends nothing more once the reversal has gone out', function () {
    $transmitter = cancellationTransmitter();
    [, $meetupEvent] = cancellationPublished();

    $meetupEvent->update(['cancelled_at' => now()]);
    expect(cancellationRepublish($transmitter))->toHaveCount(1);

    $meetupEvent->update(['cancelled_at' => null]);
    expect(cancellationRepublish($transmitter))->toHaveCount(1);

    foreach ([1, 2] as $run) {
        expect(cancellationRepublish($transmitter))->toBe([], "run {$run} re-sent something");
    }
});

/*
|--------------------------------------------------------------------------
| A cancelled event that was never published is not published now
|--------------------------------------------------------------------------
*/

it('does not publish a cancelled event that never reached a relay', function () {
    $transmitter = cancellationTransmitter();
    $meetup = cancellationMeetup();

    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'Stammtisch',
        'start' => now()->addWeek(),
        'cancelled_at' => now(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])->assertExitCode(0);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain('No unpublished items for model: MeetupEvent')
        ->assertExitCode(0);

    // Nothing of the event's kind left the process, and the row has no address — so
    // there is no relay copy to correct later, which matters because this portal emits
    // no NIP-09 deletion.
    expect($transmitter->ofKind(31923))->toBe([])
        ->and($meetupEvent->fresh()->nostr_coordinate)->toBeNull()
        ->and($meetupEvent->fresh()->nostr_payload_hash)->toBeNull();
});

/**
 * The filter is a gate, not a grave. #140 lets an organiser take the cancellation back,
 * and an event that is on again belongs in the queue like any other — otherwise the
 * cancellation would silently cost the event its Nostr publication for good.
 */
it('publishes the same event once the cancellation is taken back', function () {
    $transmitter = cancellationTransmitter();
    $meetup = cancellationMeetup();

    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'Stammtisch',
        'start' => now()->addWeek(),
        'cancelled_at' => now(),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])->assertExitCode(0);
    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])->assertExitCode(0);

    expect($transmitter->ofKind(31923))->toBe([]);

    $meetupEvent->update(['cancelled_at' => null]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain("Published calendar event for MeetupEvent #{$meetupEvent->id}")
        ->assertExitCode(0);

    expect($transmitter->ofKind(31923))->toHaveCount(1)
        ->and(RecordingNostrTransmitter::tagValue($transmitter->ofKind(31923)[0], 'title'))->toBe('Stammtisch')
        ->and(RecordingNostrTransmitter::tagValue($transmitter->ofKind(31923)[0], 'status'))->toBeNull()
        ->and($meetupEvent->fresh()->nostr_coordinate)->not->toBeNull();
});

/**
 * Issue #72's rule, applied to the column #141 added to the gate. On SQLite a
 * double-quoted identifier that matches no column degrades to a STRING LITERAL, so
 * `where "cancelled_at" is null` would be never true and NOTHING would publish — while
 * the command printed "No unpublished items" and exited 0.
 */
it('fails and names the column when meetup_events.cancelled_at is missing', function () {
    cancellationTransmitter();
    $meetup = cancellationMeetup();
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    Schema::table('meetup_events', fn ($table) => $table->dropColumn('cancelled_at'));

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain('meetup_events.cancelled_at')
        ->assertExitCode(1);
});
