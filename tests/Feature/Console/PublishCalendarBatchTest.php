<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrEventTransmitter;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use Tests\Fixtures\RecordingNostrTransmitter;

/*
|--------------------------------------------------------------------------
| nostr:publish-calendar publishes a batch per run (2026-09-17)
|--------------------------------------------------------------------------
|
| With publishing default-on the backlog was 669 upcoming events. One record per
| five-minute run would have taken 56 hours; a batch of 25 takes 27 runs. What
| the batch must NOT change: a record is only marked published after a relay
| accepted it, and a rejected send stops the run instead of pushing the rest of
| the batch at relays that are refusing.
|
| Every run passes `--sleep=0`; the pause is wall-clock time and not what these
| tests measure.
*/

const BATCH_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => BATCH_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);

    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create([
        'country_id' => $country->id,
        'latitude' => 52.5200,
        'longitude' => 13.4050,
    ]);
});

function batchMeetup(array $attributes = []): Meetup
{
    return Meetup::factory()->create(array_merge([
        'city_id' => test()->city->id,
        'nostr_publishing_enabled' => true,
    ], $attributes));
}

/**
 * @return list<MeetupEvent> in start order
 */
function batchEvents(Meetup $meetup, int $count): array
{
    $events = [];

    for ($i = 1; $i <= $count; $i++) {
        $events[] = MeetupEvent::factory()->create([
            'meetup_id' => $meetup->id,
            'start' => now()->addDays($i),
        ]);
    }

    return $events;
}

function batchTransmitter(?RecordingNostrTransmitter $transmitter = null): RecordingNostrTransmitter
{
    $transmitter ??= new RecordingNostrTransmitter;
    app()->instance(NostrEventTransmitter::class, $transmitter);

    return $transmitter;
}

function publishedEventCount(): int
{
    return MeetupEvent::query()->whereNotNull('nostr_coordinate')->count();
}

it('publishes up to --limit events in one run and leaves the rest for the next', function () {
    batchTransmitter();
    batchEvents(batchMeetup(), 7);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--limit' => 5, '--sleep' => 0])
        ->assertExitCode(0);

    expect(publishedEventCount())->toBe(5);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--limit' => 5, '--sleep' => 0])
        ->assertExitCode(0);

    expect(publishedEventCount())->toBe(7);
});

it('publishes a batch of 25 when no limit is given', function () {
    batchTransmitter();
    batchEvents(batchMeetup(), 27);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        ->assertExitCode(0);

    expect(publishedEventCount())->toBe(25);
});

it('clears a backlog in ceil(backlog / limit) runs', function () {
    batchTransmitter();
    batchEvents(batchMeetup(), 12);

    $runs = 0;

    while (MeetupEvent::query()->whereNull('nostr_coordinate')->exists() && $runs < 10) {
        $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--limit' => 5, '--sleep' => 0])
            ->assertExitCode(0);
        $runs++;
    }

    expect($runs)->toBe(3)
        ->and(publishedEventCount())->toBe(12);
});

it('batches the meetup calendars too', function () {
    batchTransmitter();

    foreach (range(1, 3) as $i) {
        batchMeetup(['name' => "Batch Meetup {$i}"]);
    }

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup', '--limit' => 2, '--sleep' => 0])
        ->assertExitCode(0);

    expect(Meetup::query()->whereNotNull('nostr_coordinate')->count())->toBe(2);
});

it('stops at the first rejected send and does not mark the records it did not send', function () {
    $transmitter = batchTransmitter(new class extends RecordingNostrTransmitter
    {
        public function transmit(Event $event, array $relayUrls): bool
        {
            parent::transmit($event, $relayUrls);

            // Accept the first two events, reject the third.
            return count($this->ofKind(31923)) <= 2;
        }
    });

    $events = batchEvents(batchMeetup(), 5);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--limit' => 25, '--sleep' => 0])
        ->assertExitCode(1);

    $pubkey = (new Key)->getPublicKey(BATCH_TEST_KEY);

    expect($transmitter->ofKind(31923))->toHaveCount(3, 'the run went on after the rejected send');

    foreach ([0, 1] as $sent) {
        expect($events[$sent]->fresh()->nostr_coordinate)->toBe("31923:{$pubkey}:meetup-event-{$events[$sent]->id}")
            ->and($events[$sent]->fresh()->nostr_payload_hash)->not->toBeNull();
    }

    foreach ([2, 3, 4] as $unsent) {
        expect($events[$unsent]->fresh()->nostr_coordinate)->toBeNull()
            ->and($events[$unsent]->fresh()->nostr_payload_hash)->toBeNull();
    }
});

it('refreshes a meetup calendar once per run, listing every event of the batch', function () {
    $transmitter = batchTransmitter();
    $pubkey = (new Key)->getPublicKey(BATCH_TEST_KEY);
    $meetup = batchMeetup(['nostr_coordinate' => "31924:{$pubkey}:meetup-1"]);
    $events = batchEvents($meetup, 3);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        ->assertExitCode(0);

    $calendars = $transmitter->ofKind(31924);

    expect($transmitter->ofKind(31923))->toHaveCount(3)
        ->and($calendars)->toHaveCount(1)
        ->and(RecordingNostrTransmitter::tagValues($calendars[0], 'a'))->toEqualCanonicalizing(
            array_map(fn (MeetupEvent $event): string => "31923:{$pubkey}:meetup-event-{$event->id}", $events),
        );
});

it('still refreshes the calendar for the events published before a rejected send', function () {
    $transmitter = batchTransmitter(new class extends RecordingNostrTransmitter
    {
        public function transmit(Event $event, array $relayUrls): bool
        {
            parent::transmit($event, $relayUrls);

            return $event->getKind() !== 31923 || count($this->ofKind(31923)) === 1;
        }
    });

    $pubkey = (new Key)->getPublicKey(BATCH_TEST_KEY);
    $meetup = batchMeetup(['nostr_coordinate' => "31924:{$pubkey}:meetup-1"]);
    $events = batchEvents($meetup, 3);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        ->assertExitCode(1);

    $calendars = $transmitter->ofKind(31924);

    expect($calendars)->toHaveCount(1)
        ->and(RecordingNostrTransmitter::tagValues($calendars[0], 'a'))
        ->toBe(["31923:{$pubkey}:meetup-event-{$events[0]->id}"]);
});

/*
 * A slow relay must not stretch one run over the next ticks: every tick starts a run
 * that selects the same unpublished records. The first send here "takes" 250 s; the run
 * then starts no further record and leaves the rest queued, without reporting failure.
 */
it('starts no new record once the run budget is used up', function () {
    $transmitter = batchTransmitter(new class extends RecordingNostrTransmitter
    {
        public function transmit(Event $event, array $relayUrls): bool
        {
            parent::transmit($event, $relayUrls);
            test()->travel(250)->seconds();

            return true;
        }
    });

    batchEvents(batchMeetup(), 4);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0])
        ->assertExitCode(0);

    expect($transmitter->ofKind(31923))->toHaveCount(1)
        ->and(publishedEventCount())->toBe(1);
});
