<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrEventTransmitter;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use Tests\Fixtures\RecordingNostrTransmitter;

/**
 * `nostr:republish-calendar` — the trigger issue #92 was missing, and the way the back
 * catalogue of issue #104 gets repaired.
 *
 * Kinds 31923 and 31924 are parameterized-replaceable: a re-send under the SAME `d` tag
 * with a NEWER `created_at` replaces the old event on every conforming relay. Those two
 * properties are what the tests below pin, because they are the entire mechanism.
 */
const REPUBLISH_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

function republishTransmitter(): RecordingNostrTransmitter
{
    $transmitter = new RecordingNostrTransmitter;
    app()->instance(NostrEventTransmitter::class, $transmitter);

    return $transmitter;
}

function republishPubkey(): string
{
    return (new Key)->getPublicKey(REPUBLISH_TEST_KEY);
}

function republishMeetup(array $attributes = []): Meetup
{
    $country = Country::factory()->create(['code' => 'us']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        // The reporter's Indianapolis coordinates, so a repaired event is visibly
        // repaired rather than merely re-sent.
        'latitude' => 39.7684,
        'longitude' => -86.1581,
    ]);

    return Meetup::factory()->create(array_merge([
        'city_id' => $city->id,
        'nostr_publishing_enabled' => true,
    ], $attributes));
}

/**
 * A meetup and one event, both taken through the real publisher so that their stored
 * coordinates and their original `created_at` are the ones production would have.
 *
 * THREE events leave the transmitter per call, not two, and that is itself the fix for
 * bug 2 at work: the calendar goes out first (empty, its event does not exist yet), the
 * kind 31923 follows, and publishing it refreshes the calendar a second time — now
 * carrying the `a` tag. The assertions below therefore count from a baseline taken
 * after setup rather than from fixed indices.
 *
 * @return array{Meetup, MeetupEvent}
 */
function republishAlreadyPublished(RecordingNostrTransmitter $transmitter): array
{
    $meetup = republishMeetup();
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    test()->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])->assertExitCode(0);
    test()->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])->assertExitCode(0);

    return [$meetup->refresh(), $meetupEvent->refresh()];
}

/**
 * The events transmitted after the given baseline — i.e. the ones the repair sent.
 *
 * @return list<Event>
 */
function republishedSince(RecordingNostrTransmitter $transmitter, int $baseline): array
{
    return array_values(array_slice($transmitter->events, $baseline));
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => REPUBLISH_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);
});

it('is a dry run by default and sends nothing at all', function () {
    $transmitter = republishTransmitter();
    republishAlreadyPublished($transmitter);
    $beforeCount = count($transmitter->events);

    $this->artisan('nostr:republish-calendar', ['--sleep' => 0])
        ->expectsOutputToContain('DRY RUN')
        ->assertExitCode(0);

    expect($transmitter->events)->toHaveCount($beforeCount);
});

/**
 * The definition-of-done assertion for issue #92: a record that already carries an
 * `nostr_coordinate` is re-sent with the SAME `d` tag and a NEWER `created_at`.
 *
 * The clock is moved forward deliberately. `created_at` is second-granular, and NIP-01
 * resolves a tie between two versions of an addressable event by keeping the one with
 * the lowest id — so a republish inside the same second as the original publish can be
 * silently discarded by the relay. Travelling makes the two publishes as far apart as
 * they are in production, where the original is days to months old.
 */
it('re-sends an already-published record with the same d tag and a newer created_at', function () {
    $transmitter = republishTransmitter();
    [$meetup, $meetupEvent] = republishAlreadyPublished($transmitter);

    $originalEvent = $transmitter->ofKind(31923)[0];
    $originalCalendar = $transmitter->ofKind(31924)[0];
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();

    $this->artisan('nostr:republish-calendar', ['--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    $republished = republishedSince($transmitter, $baseline);
    $republishedEvent = $republished[0];
    $republishedCalendar = $republished[1];

    expect($republishedEvent->getKind())->toBe(31923)
        ->and($republishedCalendar->getKind())->toBe(31924)
        ->and(RecordingNostrTransmitter::tagValue($republishedEvent, 'd'))
        ->toBe(RecordingNostrTransmitter::tagValue($originalEvent, 'd'))
        ->and(RecordingNostrTransmitter::tagValue($republishedEvent, 'd'))
        ->toBe("meetup-event-{$meetupEvent->id}")
        ->and($republishedEvent->getCreatedAt())->toBeGreaterThan($originalEvent->getCreatedAt())
        ->and(RecordingNostrTransmitter::tagValue($republishedCalendar, 'd'))
        ->toBe(RecordingNostrTransmitter::tagValue($originalCalendar, 'd'))
        ->and(RecordingNostrTransmitter::tagValue($republishedCalendar, 'd'))
        ->toBe("meetup-{$meetup->id}")
        ->and($republishedCalendar->getCreatedAt())->toBeGreaterThan($originalCalendar->getCreatedAt());
});

it('carries the repaired payload of issue 104 into the re-sent events', function () {
    $transmitter = republishTransmitter();
    [$meetup, $meetupEvent] = republishAlreadyPublished($transmitter);
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--sleep' => 0, '--force' => true])->assertExitCode(0);

    $republished = republishedSince($transmitter, $baseline);

    expect(RecordingNostrTransmitter::tagValue($republished[0], 'start_tzid'))
        ->toBe('America/Indiana/Indianapolis')
        ->and(RecordingNostrTransmitter::tagValues($republished[1], 'a'))
        ->toBe(['31923:'.republishPubkey().":meetup-event-{$meetupEvent->id}"]);
});

it('is safe to run twice, leaving the stored coordinates untouched', function () {
    $transmitter = republishTransmitter();
    [$meetup, $meetupEvent] = republishAlreadyPublished($transmitter);
    $coordinatesBefore = [$meetup->nostr_coordinate, $meetupEvent->nostr_coordinate];
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--sleep' => 0, '--force' => true])->assertExitCode(0);
    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--sleep' => 0, '--force' => true])->assertExitCode(0);

    $republished = republishedSince($transmitter, $baseline);

    expect($republished)->toHaveCount(4)
        ->and([$meetup->refresh()->nostr_coordinate, $meetupEvent->refresh()->nostr_coordinate])
        ->toBe($coordinatesBefore)
        // Same address both times, only newer — that is what "replaces in place" means.
        ->and(RecordingNostrTransmitter::tagValue($republished[2], 'd'))
        ->toBe(RecordingNostrTransmitter::tagValue($republished[0], 'd'))
        ->and($republished[2]->getCreatedAt())->toBeGreaterThan($republished[0]->getCreatedAt());
});

it('never touches a record that was never published', function () {
    $transmitter = republishTransmitter();
    $meetup = republishMeetup();
    MeetupEvent::factory()->create(['meetup_id' => $meetup->id, 'start' => now()->addWeek()]);

    $this->artisan('nostr:republish-calendar', ['--sleep' => 0, '--force' => true])
        ->expectsOutputToContain('Nothing to republish.')
        ->assertExitCode(0);

    expect($transmitter->events)->toBe([]);
});

/**
 * A re-send is a new signed event with a new id, so it is a publishing act. An
 * organiser who switched publishing off has withdrawn consent to those.
 */
it('respects a meetup that has since opted out of nostr publishing', function () {
    $transmitter = republishTransmitter();
    [$meetup] = republishAlreadyPublished($transmitter);
    $meetup->update(['nostr_publishing_enabled' => false]);
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--sleep' => 0, '--force' => true])
        ->expectsOutputToContain('Nothing to republish.')
        ->assertExitCode(0);

    expect(republishedSince($transmitter, $baseline))->toBe([]);
});

/**
 * The stored coordinate names the key the record was published under. Re-sending with a
 * different key would create a SECOND event at a new address while the old one stays on
 * the relays unchanged, so the command reports and skips instead.
 */
it('skips a record whose stored coordinate was written by another key', function () {
    $transmitter = republishTransmitter();
    [$meetup, $meetupEvent] = republishAlreadyPublished($transmitter);
    $foreignCoordinate = '31923:'.str_repeat('b2', 32).":meetup-event-{$meetupEvent->id}";
    $meetupEvent->update(['nostr_coordinate' => $foreignCoordinate]);
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--model' => 'MeetupEvent', '--sleep' => 0, '--force' => true])
        ->expectsOutputToContain('but the configured key would publish it as')
        ->assertExitCode(0);

    expect(republishedSince($transmitter, $baseline))->toBe([])
        ->and($meetupEvent->refresh()->nostr_coordinate)->toBe($foreignCoordinate);
});

it('scopes to one model when asked', function () {
    $transmitter = republishTransmitter();
    republishAlreadyPublished($transmitter);
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--model' => 'Meetup', '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    $republished = republishedSince($transmitter, $baseline);

    expect($republished)->toHaveCount(1)
        ->and($republished[0]->getKind())->toBe(31924);
});

it('scopes to one meetup, by id and by slug', function (bool $bySlug) {
    $transmitter = republishTransmitter();
    [$wanted] = republishAlreadyPublished($transmitter);
    republishAlreadyPublished($transmitter);
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', [
        '--meetup' => $bySlug ? $wanted->slug : (string) $wanted->id,
        '--sleep' => 0,
        '--force' => true,
    ])->assertExitCode(0);

    $republished = republishedSince($transmitter, $baseline);

    expect($republished)->toHaveCount(2)
        ->and(RecordingNostrTransmitter::tagValue($republished[1], 'd'))->toBe("meetup-{$wanted->id}");
})->with([
    'by id' => [false],
    'by slug' => [true],
]);

it('stops after the requested number of records', function () {
    $transmitter = republishTransmitter();
    republishAlreadyPublished($transmitter);
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--limit' => 1, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    $republished = republishedSince($transmitter, $baseline);

    // Events come before calendars, so a limit of one repairs the kind 31923 only.
    expect($republished)->toHaveCount(1)
        ->and($republished[0]->getKind())->toBe(31923);
});

it('reports failure when a relay rejects a re-send', function () {
    $transmitter = republishTransmitter();
    republishAlreadyPublished($transmitter);
    $transmitter->accepts = false;

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--sleep' => 0, '--force' => true])
        ->assertExitCode(1);
});

it('fails without a publisher key', function () {
    config(['services.nostr.publisher_key' => null]);

    $this->artisan('nostr:republish-calendar')->assertExitCode(1);
});

it('fails for an unsupported model', function () {
    $this->artisan('nostr:republish-calendar', ['--model' => 'Course'])->assertExitCode(1);
});

it('fails for a meetup filter that matches nothing', function () {
    $this->artisan('nostr:republish-calendar', ['--meetup' => 'no-such-meetup'])->assertExitCode(1);
});
