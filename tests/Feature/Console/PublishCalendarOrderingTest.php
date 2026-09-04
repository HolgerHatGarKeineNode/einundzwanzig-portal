<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrEventTransmitter;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| PublishCalendarEvents picks the most urgent record (issue #49)
|--------------------------------------------------------------------------
|
| The command publishes ONE record per run, so its ordering decides which
| record that is — and for MeetupEvent the ordering is correctness, not
| tuning. The query is gated on `start > now()`, so an event that does not
| reach the front before it begins silently leaves the result set and is never
| published at all. Until 2026-09-04 the order was `created_at DESC`, which is
| uncorrelated with the deadline.
|
| These are property tests, not snapshots. The fixture is built so that the
| three candidate orderings each pick a DIFFERENT record:
|
|     created_at DESC  -> the record created last
|     created_at ASC   -> the record created first
|     start ASC        -> the record starting soonest      <- the correct one
|
| and `it('...fixture...')` asserts that separation explicitly, so the suite
| cannot go green because two orderings happened to coincide on the data. A
| fixture where the orders agree proves nothing — the same failure shape as a
| language guard whose only sample month is spelled identically in both
| languages.
*/

const ORDERING_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

beforeEach(function () {
    config([
        'services.nostr.publisher_key' => ORDERING_TEST_KEY,
        'services.nostr.relays' => ['wss://fake.relay.test'],
    ]);

    $country = Country::factory()->create(['code' => 'de']);
    $this->city = City::factory()->create([
        'country_id' => $country->id,
        'latitude' => 52.5200,
        'longitude' => 13.4050,
    ]);
});

function orderingMeetup(array $attributes = []): Meetup
{
    return Meetup::factory()->create(array_merge([
        'city_id' => test()->city->id,
        'nostr_publishing_enabled' => true,
    ], $attributes));
}

/**
 * Four events whose creation order is the exact inverse of nothing in particular —
 * deliberately shuffled so that "newest created", "oldest created" and "starts
 * soonest" are three different records.
 *
 * @return array<string, MeetupEvent>
 */
function orderingFixture(Meetup $meetup): array
{
    $spec = [
        // label        created_at            start
        'oldest' => ['-3 days', '+30 days'],   // created FIRST,  starts LAST
        'middle' => ['-2 days', '+5 days'],
        'urgent' => ['-1 day', '+2 days'],    // starts SOONEST
        'newest' => ['-1 hour', '+10 days'],   // created LAST
    ];

    $events = [];

    foreach ($spec as $label => [$created, $start]) {
        $events[$label] = MeetupEvent::factory()->create([
            'meetup_id' => $meetup->id,
            'title' => $label,
            'created_at' => Carbon::parse($created),
            'start' => Carbon::parse($start),
        ]);
    }

    return $events;
}

function fakeAcceptingTransmitter(): void
{
    test()->mock(NostrEventTransmitter::class, function ($mock) {
        $mock->shouldReceive('transmit')->andReturn(true);
    });
}

/**
 * The title of every event that has been published, in publication order.
 *
 * @return array<int, string>
 */
function publishOrder(int $runs): array
{
    $published = [];

    for ($i = 0; $i < $runs; $i++) {
        test()->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])->run();

        $titles = MeetupEvent::query()
            ->whereNotNull('nostr_coordinate')
            ->pluck('title')
            ->all();

        $new = array_values(array_diff($titles, $published));

        if ($new === []) {
            break;
        }

        $published = array_merge($published, $new);
    }

    return $published;
}

/*
 * The guard on the fixture itself. Without it every assertion below could be green
 * because the orderings agree on this data rather than because the command is right.
 */
it('builds a fixture in which the three candidate orderings disagree', function () {
    $meetup = orderingMeetup();
    $events = orderingFixture($meetup);

    $base = MeetupEvent::query()->where('meetup_id', $meetup->id);

    $byNewest = (clone $base)->orderByDesc('created_at')->first();
    $byOldest = (clone $base)->orderBy('created_at')->first();
    $bySoonest = (clone $base)->orderBy('start')->first();

    // Positive control: an explicitly seeded created_at has to have survived the save,
    // otherwise all three orderings would collapse onto insertion order.
    expect($events['oldest']->fresh()->created_at->lt($events['newest']->fresh()->created_at))
        ->toBeTrue('the seeded created_at values did not persist');

    expect($byNewest->title)->toBe('newest')
        ->and($byOldest->title)->toBe('oldest')
        ->and($bySoonest->title)->toBe('urgent');

    // Three distinct records — so picking the right one cannot be luck.
    expect([$byNewest->title, $byOldest->title, $bySoonest->title])
        ->toBe(['newest', 'oldest', 'urgent']);
});

it('publishes the event with the earliest start first, not the newest or the oldest', function () {
    fakeAcceptingTransmitter();

    $meetup = orderingMeetup();
    orderingFixture($meetup);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->assertExitCode(0);

    $published = MeetupEvent::query()->whereNotNull('nostr_coordinate')->pluck('title')->all();

    expect($published)->toBe(['urgent']);
});

it('drains events in deadline order across successive runs', function () {
    fakeAcceptingTransmitter();

    $meetup = orderingMeetup();
    orderingFixture($meetup);

    // start ascending: +2d, +5d, +10d, +30d
    expect(publishOrder(6))->toBe(['urgent', 'middle', 'newest', 'oldest']);
});

/*
 * The property that actually matters, stated as the scenario it protects: an event
 * created long ago that starts tomorrow must not sit behind an event created this
 * morning for next month. Under `created_at DESC` it did, and if the queue outlived
 * its lead time it was never published at all.
 */
it('does not let a recently created far-off event overtake a long-planned imminent one', function () {
    fakeAcceptingTransmitter();

    $meetup = orderingMeetup();

    $imminent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'planned months ago, starts tomorrow',
        'created_at' => Carbon::parse('-120 days'),
        'start' => Carbon::parse('+1 day'),
    ]);

    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'created this morning, starts next month',
        'created_at' => Carbon::parse('-4 hours'),
        'start' => Carbon::parse('+35 days'),
    ]);

    /*
     * The third record exists only so this test can fail for the right reason.
     * With the two above alone the imminent event is ALSO the oldest, so plain
     * `created_at` ascending would pick it too and the test would pass under an
     * ordering that is still wrong — measured: a mutation to `orderBy('created_at')`
     * left this test green while the deadline was not being honoured. This record is
     * older than both and starts later than both, so ascending creation order now
     * picks it and only deadline order still picks the imminent one.
     */
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'oldest of all, starts in two months',
        'created_at' => Carbon::parse('-200 days'),
        'start' => Carbon::parse('+60 days'),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->assertExitCode(0);

    expect($imminent->fresh()->nostr_coordinate)->not->toBeNull()
        ->and(MeetupEvent::query()->whereNotNull('nostr_coordinate')->count())->toBe(1);
});

it('still refuses to publish an event that has already started, whatever the order', function () {
    fakeAcceptingTransmitter();

    $meetup = orderingMeetup();

    // Earliest `start` of all three, but in the past: deadline order must not drag it
    // back into the result set — the `start > now()` gate still governs.
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'already began',
        'start' => Carbon::parse('-1 hour'),
    ]);
    MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'title' => 'upcoming',
        'start' => Carbon::parse('+3 days'),
    ]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'MeetupEvent'])
        ->assertExitCode(0);

    expect(MeetupEvent::query()->whereNotNull('nostr_coordinate')->pluck('title')->all())
        ->toBe(['upcoming']);
});

/*
 * The Meetup side has no deadline — the query carries no time gate, so no ordering can
 * lose a record. The choice is starvation: under `created_at DESC` a newly created
 * meetup that opts in inserts itself ahead of an older one still waiting, so the older
 * one's position can get worse indefinitely. Ascending is a FIFO with a bounded wait.
 */
it('publishes the longest-waiting meetup first', function () {
    fakeAcceptingTransmitter();

    $oldest = orderingMeetup(['name' => 'waiting since 2023', 'created_at' => Carbon::parse('-400 days')]);
    orderingMeetup(['name' => 'opted in today', 'created_at' => Carbon::parse('-1 hour')]);
    orderingMeetup(['name' => 'somewhere between', 'created_at' => Carbon::parse('-30 days')]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])
        ->assertExitCode(0);

    expect(Meetup::query()->whereNotNull('nostr_coordinate')->pluck('name')->all())
        ->toBe(['waiting since 2023']);
});

it('does not starve an old meetup when newer ones keep opting in', function () {
    fakeAcceptingTransmitter();

    $old = orderingMeetup(['name' => 'the old one', 'created_at' => Carbon::parse('-400 days')]);

    // Two rounds of newcomers arriving while the queue is being worked through. Under
    // `created_at DESC` each round would jump the queue and the old one would still be
    // unpublished at the end; under ascending it goes first and leaves the queue.
    orderingMeetup(['name' => 'newcomer A', 'created_at' => Carbon::parse('-2 hours')]);

    $this->artisan('nostr:publish-calendar', ['--model' => 'Meetup'])->assertExitCode(0);

    orderingMeetup(['name' => 'newcomer B', 'created_at' => Carbon::parse('-1 hour')]);

    expect($old->fresh()->nostr_coordinate)->not->toBeNull();
});
