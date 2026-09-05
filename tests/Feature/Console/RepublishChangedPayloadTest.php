<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrEventTransmitter;
use App\Support\NostrPayloadFingerprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use swentel\nostr\Event\Event;
use swentel\nostr\Key\Key;
use Tests\Fixtures\RecordingNostrTransmitter;

/**
 * `nostr:republish-calendar --changed` — the automatic trigger of issue #92.
 *
 * The mechanism is a fingerprint of the published payload
 * ({@see NostrPayloadFingerprint}) stored per record, compared against the
 * payload the current code builds. It has two failure modes and they are opposites, so
 * a test suite that only proves "a changed payload is re-sent" is half a guard:
 *
 *  - WRONG IN ONE DIRECTION IT NEVER RE-SENDS. The fix silently does not reach the back
 *    catalogue — which is precisely the defect #92 reports, now with a mechanism that
 *    looks like it works. Pinned by the "is re-sent" tests below, and above all by the
 *    two that change the payload WITHOUT touching the record's row.
 *  - WRONG IN THE OTHER IT RE-SENDS EVERYTHING ON EVERY RUN. A burst to every relay,
 *    repeatedly, and unlike a one-off bulk repair it does not stop. Pinned by the
 *    "sends nothing" tests, the idempotence test, and the schema guard.
 *
 * NO RELAY IS CONTACTED. Every test binds {@see RecordingNostrTransmitter} into the
 * container, which records events instead of opening a websocket.
 */
const CHANGED_PAYLOAD_TEST_KEY = '9c1a7f3e5b2d84061fae93c7d05b2e814a6f37c9b0d51e2a8437fc6b9d0e5a12';

function changedTransmitter(): RecordingNostrTransmitter
{
    $transmitter = new RecordingNostrTransmitter;
    app()->instance(NostrEventTransmitter::class, $transmitter);

    return $transmitter;
}

function changedPubkey(): string
{
    return (new Key)->getPublicKey(CHANGED_PAYLOAD_TEST_KEY);
}

/**
 * A meetup in the reporter's Indianapolis, so a repaired event is visibly repaired
 * rather than merely re-sent: the city sits in America/Indiana/Indianapolis, the zone
 * issue #104 was about.
 */
function changedMeetup(array $attributes = []): Meetup
{
    $country = Country::factory()->create(['code' => 'us']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'latitude' => 39.7684,
        'longitude' => -86.1581,
    ]);

    return Meetup::factory()->create(array_merge([
        'city_id' => $city->id,
        'nostr_publishing_enabled' => true,
    ], $attributes));
}

/**
 * A meetup and its events, all taken through the REAL publisher, so their coordinates
 * and their stored fingerprints are the ones production would have.
 *
 * @return array{Meetup, Collection<int, MeetupEvent>}
 */
function changedPublished(int $events = 1, array $meetupAttributes = []): array
{
    $meetup = changedMeetup($meetupAttributes);

    $created = Collection::times($events, fn (int $n): MeetupEvent => MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeeks($n),
        'title' => null,
    ]));

    test()->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])->assertExitCode(0);

    // One record per run, by design — see PublishCalendarEvents.
    foreach ($created as $ignored) {
        test()->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])->assertExitCode(0);
    }

    return [$meetup->refresh(), $created->each->refresh()];
}

/**
 * The events transmitted after the given baseline — i.e. the ones the repair sent.
 *
 * @return list<Event>
 */
function changedSince(RecordingNostrTransmitter $transmitter, int $baseline): array
{
    return array_values(array_slice($transmitter->events, $baseline));
}

/**
 * The raw database row, past Eloquent — every column, exactly as stored.
 *
 * @return array<string, mixed>
 */
function changedRow(string $table, int $id): array
{
    return (array) DB::table($table)->where('id', $id)->first();
}

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => CHANGED_PAYLOAD_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);
});

/*
|--------------------------------------------------------------------------
| Failure mode 1: it never re-sends
|--------------------------------------------------------------------------
*/

it('re-sends a record whose published payload changed', function () {
    $transmitter = changedTransmitter();
    [$meetup] = changedPublished();
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $meetup->update(['intro' => 'A completely new introduction for the calendar.']);

    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    $republished = changedSince($transmitter, $baseline);

    // The intro is the calendar's content and nothing else's, so exactly ONE of the two
    // published records is stale. A mechanism that re-sent both would pass a test that
    // only asserted "something went out".
    expect($republished)->toHaveCount(1)
        ->and($republished[0]->getKind())->toBe(31924)
        ->and(RecordingNostrTransmitter::tagValue($republished[0], 'd'))->toBe("meetup-{$meetup->id}")
        ->and($republished[0]->getContent())->toBe($meetup->fresh()->intro);
});

/**
 * THE #104 CASE, and the one a naive `updated_at` check would miss. The zone of a
 * published event is derived from the meetup city's coordinates, so it can change with
 * no write to `meetup_events` at all — which is the same shape as the code change that
 * produced #104, where `start_tzid` moved for every published event and not one database
 * row was touched.
 *
 * The record's ENTIRE row is asserted byte-identical across the change, so no per-record
 * database state could have triggered the re-send: only the built payload could.
 */
it('re-sends a record whose payload changed with its own row untouched', function () {
    $transmitter = changedTransmitter();
    [$meetup, $events] = changedPublished();
    $meetupEvent = $events->first();
    $baseline = count($transmitter->events);

    $rowBefore = changedRow('meetup_events', $meetupEvent->id);

    $this->travel(90)->seconds();

    // The city moves to New York. Nothing in `meetup_events` is written.
    $meetup->city->update(['latitude' => 40.7128, 'longitude' => -74.0060]);

    expect(changedRow('meetup_events', $meetupEvent->id))->toBe($rowBefore);

    $this->artisan('nostr:republish-calendar', [
        '--changed' => true, '--model' => 'MeetupEvent', '--sleep' => 0, '--force' => true,
    ])->assertExitCode(0);

    $republished = changedSince($transmitter, $baseline);

    expect($republished)->toHaveCount(1)
        ->and(RecordingNostrTransmitter::tagValue($republished[0], 'start_tzid'))->toBe('America/New_York')
        ->and(RecordingNostrTransmitter::tagValue($republished[0], 'd'))->toBe("meetup-event-{$meetupEvent->id}");

    // And the re-send itself wrote nothing but the fingerprint: same row, one column on.
    $rowAfter = changedRow('meetup_events', $meetupEvent->id);
    expect(array_keys(array_diff_assoc($rowAfter, $rowBefore)))->toBe(['nostr_payload_hash']);
});

/**
 * The back catalogue of issues #69 and #104: a record with a coordinate and no stored
 * fingerprint, i.e. one published before the column existed. NULL means UNKNOWN, and
 * unknown is treated as stale — the alternative would mark exactly those records as up
 * to date and reproduce the defect the issue reports.
 *
 * Nothing is backfilled, so the repair costs one idempotent re-send per record and then
 * stops: the second run below sends nothing.
 */
it('re-sends a record published before the fingerprint column existed, exactly once', function () {
    $transmitter = changedTransmitter();
    [$meetup, $events] = changedPublished();
    $meetupEvent = $events->first();

    // The starting state of the production catalogue: address known, payload unknown.
    // Written past Eloquent so `updated_at` stays where it was.
    DB::table('meetups')->where('id', $meetup->id)->update(['nostr_payload_hash' => null]);
    DB::table('meetup_events')->where('id', $meetupEvent->id)->update(['nostr_payload_hash' => null]);

    $baseline = count($transmitter->events);
    $rowBefore = changedRow('meetup_events', $meetupEvent->id);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->expectsOutputToContain('Checked 2 published record(s): 2 carry a payload this code no longer builds.')
        ->assertExitCode(0);

    $firstRun = changedSince($transmitter, $baseline);

    expect($firstRun)->toHaveCount(2)
        ->and($firstRun[0]->getKind())->toBe(31923)
        ->and($firstRun[1]->getKind())->toBe(31924)
        // The payload the repair carries is today's, wrong-zone defect included: the
        // whole point of reaching the back catalogue at all.
        ->and(RecordingNostrTransmitter::tagValue($firstRun[0], 'start_tzid'))->toBe('America/Indiana/Indianapolis')
        ->and(RecordingNostrTransmitter::tagValues($firstRun[1], 'a'))
        ->toBe(['31923:'.changedPubkey().":meetup-event-{$meetupEvent->id}"])
        // Nothing but the fingerprint was written, so this is not a row-level trigger
        // that happens to fire once.
        ->and(array_keys(array_diff_assoc(changedRow('meetup_events', $meetupEvent->id), $rowBefore)))
        ->toBe(['nostr_payload_hash']);

    $second = count($transmitter->events);
    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    expect(changedSince($transmitter, $second))->toBe([]);
});

/**
 * A calendar refresh that the relay rejected leaves the fingerprint of the payload it
 * really last carried, so the scheduled `--changed` run repairs it — where before this
 * change the warning was the end of the story until the meetup's next event or an
 * operator.
 */
it('repairs a calendar whose inline refresh was rejected', function () {
    $transmitter = changedTransmitter();
    $meetup = changedMeetup();
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'title' => null,
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])->assertExitCode(0);

    $transmitter->rejectedKinds = [31924];
    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->expectsOutputToContain('could not refresh the calendar')
        ->assertExitCode(0);
    $transmitter->rejectedKinds = [];

    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    $republished = changedSince($transmitter, $baseline);

    expect($republished)->toHaveCount(1)
        ->and($republished[0]->getKind())->toBe(31924)
        ->and(RecordingNostrTransmitter::tagValues($republished[0], 'a'))
        ->toBe(['31923:'.changedPubkey().":meetup-event-{$meetupEvent->id}"]);
});

/**
 * A relay that refuses the re-send must leave the fingerprint alone, or the record would
 * be marked repaired without anything having reached a relay — a silent loss dressed as
 * a success.
 */
it('keeps a record stale when the re-send failed', function () {
    $transmitter = changedTransmitter();
    [$meetup] = changedPublished();
    $meetup->update(['intro' => 'Changed while the relays are down.']);

    $transmitter->accepts = false;
    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->assertExitCode(1);

    $transmitter->accepts = true;
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    expect(changedSince($transmitter, $baseline))->toHaveCount(1)
        ->and(changedSince($transmitter, $baseline)[0]->getKind())->toBe(31924);
});

/*
|--------------------------------------------------------------------------
| Failure mode 2: it re-sends everything, on every run
|--------------------------------------------------------------------------
*/

it('sends nothing when no published payload changed', function () {
    $transmitter = changedTransmitter();
    [$meetup, $events] = changedPublished();
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->expectsOutputToContain('Checked 2 published record(s): 0 carry a payload this code no longer builds.')
        ->expectsOutputToContain('Nothing to republish.')
        ->assertExitCode(0);

    expect(changedSince($transmitter, $baseline))->toBe([])
        // The publisher recorded what it sent, which is what makes the line above true.
        ->and($meetup->fresh()->nostr_payload_hash)->toMatch('/^[0-9a-f]{64}$/')
        ->and($events->first()->fresh()->nostr_payload_hash)->toMatch('/^[0-9a-f]{64}$/');
});

/**
 * IDEMPOTENCE, the property that keeps a real change from becoming a standing burst.
 * The first run repairs, the second finds nothing — measured over three consecutive runs
 * because "twice" cannot tell a self-terminating mechanism from one that alternates.
 */
it('re-sends a changed record once and nothing on the runs after it', function () {
    $transmitter = changedTransmitter();
    [$meetup] = changedPublished(2);

    $this->travel(90)->seconds();
    $meetup->update(['name' => 'Renamed Bitcoin Meetup Indianapolis']);

    $baseline = count($transmitter->events);
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    // The name is the calendar's title AND the title of both events, which carry no
    // title of their own — so all three published records are stale.
    expect(changedSince($transmitter, $baseline))->toHaveCount(3);

    foreach ([2, 3] as $run) {
        $before = count($transmitter->events);
        $this->travel(90)->seconds();
        $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
            ->assertExitCode(0);

        expect(changedSince($transmitter, $before))->toBe([], "run {$run} re-sent something");
    }
});

/**
 * The trigger is the payload, never `updated_at`. This is the direction in which a naive
 * `updated_at` check does not merely miss a repair but manufactures traffic: any write
 * that leaves the published payload alone — an organiser toggling RSVP, a background job
 * touching a row — would re-broadcast the record to every relay.
 */
it('does not re-send a record that was written to without changing its payload', function () {
    $transmitter = changedTransmitter();
    [$meetup, $events] = changedPublished();
    $meetupEvent = $events->first();
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();

    // `rsvp_enabled` and `attendees` appear in no published tag. Both rows are really
    // written: `updated_at` moves on both.
    $publishedAtMeetup = $meetup->fresh()->updated_at;
    $publishedAtEvent = $meetupEvent->fresh()->updated_at;

    $meetup->update(['rsvp_enabled' => ! $meetup->rsvp_enabled]);
    $meetupEvent->update(['attendees' => ['npub1someone']]);

    expect($meetup->fresh()->updated_at->greaterThan($publishedAtMeetup))->toBeTrue()
        ->and($meetupEvent->fresh()->updated_at->greaterThan($publishedAtEvent))->toBeTrue();

    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->expectsOutputToContain('Checked 2 published record(s): 0 carry a payload this code no longer builds.')
        ->assertExitCode(0);

    expect(changedSince($transmitter, $baseline))->toBe([]);
});

it('is a dry run by default in changed mode too', function () {
    $transmitter = changedTransmitter();
    [$meetup] = changedPublished();
    $meetup->update(['intro' => 'Changed, but nobody said --force.']);
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0])
        ->expectsOutputToContain('DRY RUN')
        ->assertExitCode(0);

    expect(changedSince($transmitter, $baseline))->toBe([])
        // And a dry run must not record a fingerprint either, or the next real run
        // would consider the record repaired without a single event having left.
        ->and($meetup->fresh()->nostr_payload_hash)->not->toBeNull();

    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    expect(changedSince($transmitter, $baseline))->toHaveCount(1);
});

/**
 * A re-send is a new signed event, so it is a publishing act; an organiser who switched
 * publishing off has withdrawn consent to those. The stale payload is not an exception
 * to that.
 */
it('leaves a stale record alone when its meetup opted out of publishing', function () {
    $transmitter = changedTransmitter();
    [$meetup] = changedPublished();
    $meetup->update(['intro' => 'Changed after opting out.', 'nostr_publishing_enabled' => false]);
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->expectsOutputToContain('Checked 0 published record(s)')
        ->assertExitCode(0);

    expect(changedSince($transmitter, $baseline))->toBe([]);
});

/**
 * An unmigrated database must stop this command rather than be absorbed by it. Without
 * the column every record reads back a null fingerprint, counts as stale, and the
 * hourly entry re-sends the whole catalogue on every run for ever — the exact burst the
 * mechanism exists to prevent, arriving through a missing migration.
 */
it('refuses to run in changed mode without the fingerprint column', function () {
    changedTransmitter();
    changedPublished();

    Schema::table('meetups', fn ($table) => $table->dropColumn('nostr_payload_hash'));

    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->expectsOutputToContain('would re-send the whole catalogue on every run')
        ->assertExitCode(1);

    // The default mode does not read the column and keeps working, so the guard is
    // scoped to the mode that needs it.
    $this->artisan('nostr:republish-calendar', ['--sleep' => 0])
        ->expectsOutputToContain('DRY RUN')
        ->assertExitCode(0);
});

/*
|--------------------------------------------------------------------------
| Batching: the filter runs before the limit
|--------------------------------------------------------------------------
*/

/**
 * The scheduled entry runs with `--limit=10`, so the limit has to cap WORK DONE and not
 * ROWS LOOKED AT. Applied the other way round, a batch could be filled with records that
 * are already up to date, send nothing, and pick the same rows again on every run — a
 * mechanism that never finishes and never says so.
 */
it('fills the batch with stale records only, and drains across runs', function () {
    $transmitter = changedTransmitter();
    [$meetup, $events] = changedPublished(2);

    $this->travel(90)->seconds();
    $meetup->update(['name' => 'Renamed Bitcoin Meetup Indianapolis']);

    $dTagsSent = [];

    foreach ([1, 2, 3] as $run) {
        $before = count($transmitter->events);
        $this->travel(90)->seconds();
        $this->artisan('nostr:republish-calendar', ['--changed' => true, '--limit' => 1, '--sleep' => 0, '--force' => true])
            ->assertExitCode(0);

        $sent = changedSince($transmitter, $before);
        expect($sent)->toHaveCount(1, "run {$run} did not send exactly one record");
        $dTagsSent[] = RecordingNostrTransmitter::tagValue($sent[0], 'd');
    }

    // Events before calendars, earliest start first — and every record exactly once.
    expect($dTagsSent)->toBe([
        "meetup-event-{$events[0]->id}",
        "meetup-event-{$events[1]->id}",
        "meetup-{$meetup->id}",
    ]);

    $after = count($transmitter->events);
    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--limit' => 1, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    expect(changedSince($transmitter, $after))->toBe([]);
});

/**
 * A record published under a rotated key can never be re-sent — its address belongs to
 * another key. Leaving it in the candidate set would let a handful of them occupy the
 * batch on every run and starve the records that CAN be repaired, so it is dropped
 * during the scan and reported, not carried into the limit.
 */
it('does not let an unrepairable key mismatch occupy the batch', function () {
    $transmitter = changedTransmitter();
    [$meetup, $events] = changedPublished(2);

    DB::table('meetup_events')->where('id', $events[0]->id)->update([
        'nostr_coordinate' => '31923:'.str_repeat('b2', 32).":meetup-event-{$events[0]->id}",
    ]);

    $this->travel(90)->seconds();
    $meetup->update(['name' => 'Renamed Bitcoin Meetup Indianapolis']);
    $baseline = count($transmitter->events);

    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--limit' => 1, '--sleep' => 0, '--force' => true])
        ->expectsOutputToContain('but the configured key would publish it as')
        ->assertExitCode(0);

    $sent = changedSince($transmitter, $baseline);

    // The batch of one went to the SECOND event, not to the unrepairable first one.
    expect($sent)->toHaveCount(1)
        ->and(RecordingNostrTransmitter::tagValue($sent[0], 'd'))->toBe("meetup-event-{$events[1]->id}");
});

/*
|--------------------------------------------------------------------------
| The manual command keeps its behaviour
|--------------------------------------------------------------------------
*/

/**
 * `--changed` is opt-in. Without it the command is what it was: a blunt operator repair
 * that re-sends everything published, whether or not the payload moved.
 */
it('still re-sends unchanged records when --changed is not given', function () {
    $transmitter = changedTransmitter();
    changedPublished();
    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--sleep' => 0, '--force' => true])
        ->doesntExpectOutputToContain('carry a payload this code no longer builds')
        ->assertExitCode(0);

    expect(changedSince($transmitter, $baseline))->toHaveCount(2);
});

/**
 * A forced manual repair records what it sent, so the hourly `--changed` entry does not
 * immediately re-send the whole catalogue behind the operator's back.
 */
it('records the fingerprint on a forced default-mode run', function () {
    $transmitter = changedTransmitter();
    [$meetup] = changedPublished();
    DB::table('meetups')->where('id', $meetup->id)->update(['nostr_payload_hash' => null]);
    DB::table('meetup_events')->where('meetup_id', $meetup->id)->update(['nostr_payload_hash' => null]);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--sleep' => 0, '--force' => true])->assertExitCode(0);

    $baseline = count($transmitter->events);

    $this->travel(90)->seconds();
    $this->artisan('nostr:republish-calendar', ['--changed' => true, '--sleep' => 0, '--force' => true])
        ->assertExitCode(0);

    expect(changedSince($transmitter, $baseline))->toBe([]);
});
