<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrEventTransmitter;
use App\Support\NostrRelayReader;
use Illuminate\Support\Facades\Cache;
use swentel\nostr\Key\Key;
use swentel\nostr\Sign\Sign;
use Tests\Fixtures\RecordingNostrTransmitter;
use Tests\Fixtures\ScriptedNostrRelayReader;

/**
 * `nostr:rebroadcast-calendar` — reads the relays, then sends each relay what it lacks.
 *
 * The rule these tests are built around is the operator's, 2026-11-17: ONLY UPCOMING,
 * non-cancelled dates and the calendars of meetups that have one are ever re-sent.
 * No relay is real here: the reader answers from a script, the transmitter records.
 */
const REBROADCAST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';
const REBROADCAST_RELAYS = ['wss://a.relay.test', 'wss://b.relay.test', 'wss://c.relay.test'];

function rebroadcastPubkey(): string
{
    return (new Key)->getPublicKey(REBROADCAST_KEY);
}

function rebroadcastMeetup(): Meetup
{
    $city = City::factory()->create(['country_id' => Country::factory()->create(['code' => 'de'])->id]);
    $meetup = Meetup::factory()->create(['city_id' => $city->id, 'nostr_publishing_enabled' => true]);
    $meetup->forceFill(['nostr_coordinate' => '31924:'.rebroadcastPubkey().':meetup-'.$meetup->id])->save();

    return $meetup;
}

function rebroadcastDate(Meetup $meetup, array $attributes = []): MeetupEvent
{
    $event = MeetupEvent::factory()->create(array_merge(['meetup_id' => $meetup->id, 'start' => now()->addWeek()], $attributes));
    $event->forceFill(['nostr_coordinate' => '31923:'.rebroadcastPubkey().':meetup-event-'.$event->id])->save();

    return $event;
}

/**
 * The signed event a relay would hold for a record, as the array a relay returns.
 *
 * @return array<string, mixed>
 */
function rebroadcastCopy(Meetup|MeetupEvent $record, ?int $createdAt = null, ?string $content = null): array
{
    $event = $record instanceof Meetup
        ? NostrCalendarEventFactory::forMeetup($record->fresh())
        : NostrCalendarEventFactory::forMeetupEvent($record->fresh(), rebroadcastPubkey());

    if ($createdAt !== null) {
        $event->setCreatedAt($createdAt);
    }

    if ($content !== null) {
        $event->setContent($content);
    }

    (new Sign)->signEvent($event, REBROADCAST_KEY);

    return $event->toArray();
}

/**
 * @param  array<string, list<array<string, mixed>>|null>  $relays  relay => held events, null = relay cannot be read
 * @return array{0: ScriptedNostrRelayReader, 1: RecordingNostrTransmitter}
 */
function rebroadcastWorld(array $relays): array
{
    $reader = new ScriptedNostrRelayReader;
    $reader->relays = $relays;
    app()->instance(NostrRelayReader::class, $reader);

    $transmitter = new RecordingNostrTransmitter;
    app()->instance(NostrEventTransmitter::class, $transmitter);

    return [$reader, $transmitter];
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => REBROADCAST_KEY,
        'services.nostr.relays' => REBROADCAST_RELAYS,
    ]);
    Cache::flush();
});

it('never sends a past date, whatever the relays hold', function (string $relayState) {
    $meetup = rebroadcastMeetup();
    $past = rebroadcastDate($meetup, ['start' => now()->subDay()]);

    $held = match ($relayState) {
        'nothing' => [],
        'a stale copy' => [rebroadcastCopy($past, now()->subMonth()->getTimestamp(), 'outdated text')],
        'the current copy' => [rebroadcastCopy($past)],
    };

    [$reader, $transmitter] = rebroadcastWorld(['wss://a.relay.test' => $held, 'wss://b.relay.test' => [], 'wss://c.relay.test' => []]);

    $this->artisan('nostr:rebroadcast-calendar', ['--force' => true, '--sleep' => 0, '--limit' => 0])->assertExitCode(0);

    // Not sent — neither the date nor the calendar of a meetup that has nothing ahead —
    // and not even asked for.
    expect($transmitter->events)->toBe([])
        ->and($reader->reads)->toBe([]);
})->with(['nothing', 'a stale copy', 'the current copy']);

it('never sends a cancelled date, even an upcoming one', function () {
    $meetup = rebroadcastMeetup();
    rebroadcastDate($meetup, ['cancelled_at' => now()]);

    [, $transmitter] = rebroadcastWorld(array_fill_keys(REBROADCAST_RELAYS, []));

    $this->artisan('nostr:rebroadcast-calendar', ['--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect($transmitter->events)->toBe([]);
});

it('sends an upcoming date only to the relays that lack it, and forwards the copy another relay holds unchanged', function () {
    $meetup = rebroadcastMeetup();
    $date = rebroadcastDate($meetup);
    $copy = rebroadcastCopy($date, now()->subDays(3)->getTimestamp());

    [, $transmitter] = rebroadcastWorld([
        'wss://a.relay.test' => [$copy],   // holds it
        'wss://b.relay.test' => [],        // lacks it
        'wss://c.relay.test' => null,      // cannot be read: nothing is known, nothing is sent
    ]);

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect($transmitter->events)->toHaveCount(1)
        ->and($transmitter->relayLists[0])->toBe(['wss://b.relay.test'])
        // Same id, same signature, same created_at: nothing was re-signed, so no client
        // sees the date change and no relay gets an older or newer version.
        ->and($transmitter->events[0]->getId())->toBe($copy['id'])
        ->and($transmitter->events[0]->getSignature())->toBe($copy['sig'])
        ->and($transmitter->events[0]->getCreatedAt())->toBe($copy['created_at']);
});

it('signs one new event, newer than every copy seen, when no relay holds the current payload', function () {
    $meetup = rebroadcastMeetup();
    $date = rebroadcastDate($meetup);
    $stale = rebroadcastCopy($date, now()->addMinutes(5)->getTimestamp(), 'outdated text');

    [, $transmitter] = rebroadcastWorld([
        'wss://a.relay.test' => [$stale],
        'wss://b.relay.test' => [],
        'wss://c.relay.test' => [],
    ]);

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect($transmitter->events)->toHaveCount(1)
        ->and($transmitter->relayLists[0])->toBe(REBROADCAST_RELAYS)
        ->and($transmitter->events[0]->getId())->not->toBe($stale['id'])
        // An older copy must not win on any relay: one second past the newest seen.
        ->and($transmitter->events[0]->getCreatedAt())->toBe($stale['created_at'] + 1)
        ->and($transmitter->events[0]->getContent())->not->toBe('outdated text');
});

it('does not count a copy that fails verification or is signed by another key', function () {
    $meetup = rebroadcastMeetup();
    $date = rebroadcastDate($meetup);

    $forged = rebroadcastCopy($date);
    $forged['content'] = 'tampered';

    [, $transmitter] = rebroadcastWorld([
        'wss://a.relay.test' => [$forged],
        'wss://b.relay.test' => [rebroadcastCopy($date)],
        'wss://c.relay.test' => [rebroadcastCopy($date)],
    ]);

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect($transmitter->relayLists)->toBe([['wss://a.relay.test']]);
});

it('sends the calendar of a meetup with an upcoming date and leaves the others alone', function () {
    $withUpcoming = rebroadcastMeetup();
    rebroadcastDate($withUpcoming);

    $pastOnly = rebroadcastMeetup();
    rebroadcastDate($pastOnly, ['start' => now()->subWeek()]);

    [, $transmitter] = rebroadcastWorld(array_fill_keys(REBROADCAST_RELAYS, []));

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'Meetup', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    expect($transmitter->events)->toHaveCount(1)
        ->and(RecordingNostrTransmitter::tagValue($transmitter->events[0], 'd'))->toBe('meetup-'.$withUpcoming->id);
});

it('offers one relay no more than --per-relay events in a run', function () {
    $meetup = rebroadcastMeetup();
    rebroadcastDate($meetup);
    rebroadcastDate($meetup);
    rebroadcastDate($meetup);

    [, $transmitter] = rebroadcastWorld(array_fill_keys(REBROADCAST_RELAYS, []));

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0, '--per-relay' => 2])
        ->assertExitCode(0);

    expect($transmitter->events)->toHaveCount(2);
});

it('is a dry run without --force: it reads, prints what is missing and sends nothing', function () {
    $meetup = rebroadcastMeetup();
    $date = rebroadcastDate($meetup);

    [$reader, $transmitter] = rebroadcastWorld([
        'wss://a.relay.test' => [rebroadcastCopy($date)],
        'wss://b.relay.test' => [],
        'wss://c.relay.test' => [],
    ]);

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain('Nothing was sent. 2 event copies are missing')
        ->assertExitCode(0);

    expect($transmitter->events)->toBe([])
        ->and($reader->reads)->toHaveCount(3);
});

it('walks on from where the last run stopped', function () {
    $meetup = rebroadcastMeetup();
    $first = rebroadcastDate($meetup);
    $second = rebroadcastDate($meetup);

    [, $transmitter] = rebroadcastWorld(array_fill_keys(REBROADCAST_RELAYS, []));

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0, '--limit' => 1])->assertExitCode(0);
    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0, '--limit' => 1])->assertExitCode(0);

    expect(array_map(fn ($event) => RecordingNostrTransmitter::tagValue($event, 'd'), $transmitter->events))
        ->toBe(['meetup-event-'.$first->id, 'meetup-event-'.$second->id]);
});

it('fails when no relay can be read, and sends nothing', function () {
    $meetup = rebroadcastMeetup();
    rebroadcastDate($meetup);

    [, $transmitter] = rebroadcastWorld(array_fill_keys(REBROADCAST_RELAYS, null));

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0])->assertExitCode(1);

    expect($transmitter->events)->toBe([]);
});

it('lists only upcoming dates in a calendar, not the meetup\'s whole past', function () {
    $meetup = rebroadcastMeetup();
    $past = rebroadcastDate($meetup, ['start' => now()->subMonth()]);
    $upcoming = rebroadcastDate($meetup);

    $calendar = NostrCalendarEventFactory::forMeetup($meetup->fresh());

    expect(RecordingNostrTransmitter::tagValues($calendar, 'a'))->toBe([$upcoming->nostr_coordinate])
        ->and(RecordingNostrTransmitter::tagValues($calendar, 'a'))->not->toContain($past->nostr_coordinate);
});

it('does not send a date that began while the relays were being read', function () {
    $meetup = rebroadcastMeetup();
    rebroadcastDate($meetup, ['start' => now()->addSeconds(30)]);

    [$reader, $transmitter] = rebroadcastWorld(array_fill_keys(REBROADCAST_RELAYS, []));
    $reader->secondsPerRead = 60;

    $this->artisan('nostr:rebroadcast-calendar', ['--model' => 'MeetupEvent', '--force' => true, '--sleep' => 0])->assertExitCode(0);

    // Selected as upcoming, past by the time it would leave: the last gate holds.
    expect($reader->reads)->toHaveCount(3)
        ->and($transmitter->events)->toBe([]);
});
