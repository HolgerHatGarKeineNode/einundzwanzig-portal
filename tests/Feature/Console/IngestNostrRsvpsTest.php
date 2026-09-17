<?php

use App\Console\Commands\Nostr\IngestNostrRsvps;
use App\Models\City;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\MeetupEventNostrRsvp;
use App\Models\User;
use App\Support\NostrRelayReader;
use App\Support\NostrRsvpFold;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use swentel\nostr\Key\Key;
use Tests\Fixtures\NostrTestEvents;
use Tests\Fixtures\ScriptedNostrRelayReader;

/*
|--------------------------------------------------------------------------
| nostr:ingest-rsvps — scope, relay verdicts, cursor, storage, linking (D12)
|--------------------------------------------------------------------------
|
| NO RELAY IS CONTACTED: every test binds ScriptedNostrRelayReader. The pure rules of the
| fold have their own unit tests (tests/Unit/Support/NostrRsvpFoldTest.php); this file
| covers what only the command decides — which events are asked for, what an unknown
| relay changes (nothing), where the cursor goes, and what ends up in which table.
*/

const INGEST_RELAY_ONE = 'wss://relay-one.test';

const INGEST_RELAY_TWO = 'wss://relay-two.test';

beforeEach(function () {
    config()->set('services.nostr.publisher_key', NostrTestEvents::PUBLISHER_SECRET);
    config()->set('services.nostr.relays', [INGEST_RELAY_ONE, INGEST_RELAY_TWO]);

    $this->reader = new ScriptedNostrRelayReader;
    app()->instance(NostrRelayReader::class, $this->reader);
});

function ingestPublishedEvent(array $meetup = [], array $event = []): MeetupEvent
{
    $meetupEvent = MeetupEvent::factory()->create([
        // One shared city: the country factory draws unique codes and runs dry past ~20 meetups.
        'meetup_id' => Meetup::factory()->create(array_merge([
            'city_id' => (City::query()->first() ?? City::factory()->create())->id,
            'rsvp_enabled' => true,
            'attendees_public' => true,
        ], $meetup))->id,
        'start' => now()->addDays(3),
        'attendees' => ['id_999|Portal Pleb'],
        'might_attendees' => [],
        ...$event,
    ]);

    $meetupEvent->forceFill(['nostr_coordinate' => NostrTestEvents::coordinate($meetupEvent->id)])->save();

    return $meetupEvent;
}

function ingestNpubOf(string $secret): string
{
    return (new Key)->convertPublicKeyToBech32(NostrTestEvents::pubkey($secret));
}

it('stores a valid RSVP and links it to the account that owns the key', function () {
    $event = ingestPublishedEvent();
    $linked = User::factory()->create(['nostr' => ingestNpubOf(NostrTestEvents::ATTENDEE_SECRET)]);

    $mine = NostrTestEvents::rsvp($event->id, 'accepted', now()->subMinute()->timestamp);
    $stranger = NostrTestEvents::rsvp($event->id, 'tentative', now()->subMinute()->timestamp, NostrTestEvents::OTHER_ATTENDEE_SECRET);
    $this->reader->relays = [INGEST_RELAY_ONE => [$mine, $stranger], INGEST_RELAY_TWO => [$mine]];

    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect(MeetupEventNostrRsvp::query()->orderBy('status')->get(['meetup_event_id', 'pubkey', 'nostr_event_id', 'status', 'user_id'])->toArray())->toBe([
        [
            'meetup_event_id' => $event->id,
            'pubkey' => NostrTestEvents::pubkey(NostrTestEvents::ATTENDEE_SECRET),
            'nostr_event_id' => $mine['id'],
            'status' => 'accepted',
            'user_id' => $linked->id,
        ],
        [
            'meetup_event_id' => $event->id,
            'pubkey' => NostrTestEvents::pubkey(NostrTestEvents::OTHER_ATTENDEE_SECRET),
            'nostr_event_id' => $stranger['id'],
            'status' => 'tentative',
            'user_id' => null,
        ],
    ]);
});

it('never writes the attendee JSON of the event', function () {
    $event = ingestPublishedEvent();
    $rawBefore = DB::table('meetup_events')->where('id', $event->id)->first(['attendees', 'might_attendees', 'updated_at']);

    $this->reader->relays = [
        INGEST_RELAY_ONE => [NostrTestEvents::rsvp($event->id, 'accepted', now()->subMinute()->timestamp)],
        INGEST_RELAY_TWO => [],
    ];

    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect(MeetupEventNostrRsvp::query()->count())->toBe(1)
        ->and(DB::table('meetup_events')->where('id', $event->id)->first(['attendees', 'might_attendees', 'updated_at']))->toEqual($rawBefore);
});

it('changes no count and moves no cursor when no relay confirms EOSE', function () {
    $event = ingestPublishedEvent();
    $stored = MeetupEventNostrRsvp::factory()->create(['meetup_event_id' => $event->id, 'status' => 'accepted']);
    $cursorBefore = ['last_ok' => now()->subMinutes(5)->timestamp, 'last_full' => now()->subMinutes(20)->timestamp];
    Cache::forever('nostr:ingest-rsvps:cursor:'.sha1(INGEST_RELAY_ONE), $cursorBefore);

    $countsBefore = $this->getJson('/api/meetup-events')->json('0');

    // null = the relay never confirms the end of its stored events.
    $this->reader->relays = [INGEST_RELAY_ONE => null, INGEST_RELAY_TWO => null];

    $this->artisan('nostr:ingest-rsvps')->assertFailed();

    $countsAfter = $this->getJson('/api/meetup-events')->json('0');

    expect(MeetupEventNostrRsvp::query()->sole()->toArray())->toEqual($stored->fresh()->toArray())
        ->and([$countsAfter['attendees'], $countsAfter['nostr_attendees']])->toBe([$countsBefore['attendees'], $countsBefore['nostr_attendees']])
        ->and($countsAfter['nostr_attendees'])->toBe(1)
        ->and(Cache::get('nostr:ingest-rsvps:cursor:'.sha1(INGEST_RELAY_ONE)))->toBe($cursorBefore)
        ->and(Cache::get('nostr:ingest-rsvps:cursor:'.sha1(INGEST_RELAY_TWO)))->toBeNull();
});

it('uses only the relays that answered completely, and moves only their cursors', function () {
    $this->travelTo(now()->startOfMinute());
    $event = ingestPublishedEvent();

    $onlyOnTheBrokenRelay = NostrTestEvents::rsvp($event->id, 'accepted', now()->subMinute()->timestamp);
    $onTheGoodRelay = NostrTestEvents::rsvp($event->id, 'tentative', now()->subMinute()->timestamp, NostrTestEvents::OTHER_ATTENDEE_SECRET);

    // The scripted "unknown" relay returns nothing — the real one would have returned a
    // partial list, which the reader turns into the same empty, unknown answer.
    $this->reader->relays = [INGEST_RELAY_ONE => null, INGEST_RELAY_TWO => [$onTheGoodRelay]];

    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect(MeetupEventNostrRsvp::query()->pluck('nostr_event_id')->all())->toBe([$onTheGoodRelay['id']])
        ->and(MeetupEventNostrRsvp::query()->where('nostr_event_id', $onlyOnTheBrokenRelay['id'])->exists())->toBeFalse()
        ->and(Cache::get('nostr:ingest-rsvps:cursor:'.sha1(INGEST_RELAY_ONE)))->toBeNull()
        ->and(Cache::get('nostr:ingest-rsvps:cursor:'.sha1(INGEST_RELAY_TWO)))->toBe(['last_ok' => now()->timestamp, 'last_full' => now()->timestamp]);
});

it('reads incrementally from the cursor minus an hour, and in full once an hour', function () {
    $this->travelTo(now()->startOfMinute());
    config()->set('services.nostr.relays', [INGEST_RELAY_ONE]);
    ingestPublishedEvent();
    $this->reader->relays = [INGEST_RELAY_ONE => []];

    $firstRun = now()->timestamp;
    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    $this->travel(5)->minutes();
    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    $this->travel(56)->minutes();
    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    $sinceOf = fn (array $read): array => array_values(array_unique(array_map(fn (array $filter) => $filter['since'] ?? null, $read['filters'])));

    expect($this->reader->reads)->toHaveCount(3)
        ->and($sinceOf($this->reader->reads[0]))->toBe([null])
        ->and($sinceOf($this->reader->reads[1]))->toBe([$firstRun - IngestNostrRsvps::CURSOR_OVERLAP_SECONDS])
        ->and($sinceOf($this->reader->reads[2]))->toBe([null]);
});

it('asks for the published coordinates in chunks of twenty, the publisher tag and nothing out of scope', function () {
    config()->set('services.nostr.relays', [INGEST_RELAY_ONE]);
    $inScope = collect(range(1, 21))->map(fn () => ingestPublishedEvent());
    ingestPublishedEvent(['attendees_public' => false]);
    ingestPublishedEvent(['rsvp_enabled' => false]);
    ingestPublishedEvent(event: ['cancelled_at' => now()]);
    ingestPublishedEvent(event: ['start' => now()->subDays(2)]);
    MeetupEvent::factory()->create(['start' => now()->addDay()]); // never published
    // Published under a key that is not the configured one (a rotated publisher key).
    $otherKey = ingestPublishedEvent();
    $otherKey->forceFill(['nostr_coordinate' => NostrTestEvents::coordinate($otherKey->id, NostrTestEvents::FOREIGN_PUBLISHER_SECRET)])->save();
    $this->reader->relays = [INGEST_RELAY_ONE => []];

    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    $filters = $this->reader->reads[0]['filters'];
    $asked = collect($filters)->pluck('#a')->filter()->flatten()->sort()->values()->all();

    expect($asked)->toBe($inScope->pluck('nostr_coordinate')->sort()->values()->all())
        ->and(collect($filters)->pluck('#a')->filter()->map(fn (array $chunk) => count($chunk))->all())->toBe([20, 1])
        ->and(collect($filters)->firstWhere('#p'))->toBe([
            'kinds' => [NostrRsvpFold::KIND_RSVP],
            '#p' => [NostrTestEvents::pubkey(NostrTestEvents::PUBLISHER_SECRET)],
            'limit' => IngestNostrRsvps::FILTER_LIMIT,
        ]);
});

it('opens no socket when no published event accepts RSVPs', function () {
    ingestPublishedEvent(['attendees_public' => false]);

    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect($this->reader->reads)->toBe([]);
});

it('ignores an RSVP to an event whose attendees are not public, and says why', function () {
    Log::spy();
    $hidden = ingestPublishedEvent(['attendees_public' => false]);
    ingestPublishedEvent();

    // Reached through the #p filter, the way a client that tags the publisher would.
    $rsvp = NostrTestEvents::rsvp($hidden->id, 'accepted', now()->subMinute()->timestamp, extraTags: [
        ['p', NostrTestEvents::pubkey(NostrTestEvents::PUBLISHER_SECRET)],
    ]);
    $this->reader->relays = [INGEST_RELAY_ONE => [$rsvp], INGEST_RELAY_TWO => []];

    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect(MeetupEventNostrRsvp::query()->count())->toBe(0);

    Log::shouldHaveReceived('info')
        ->withArgs(fn (string $message, array $context): bool => $message === 'nostr:ingest-rsvps finished'
            && $context['dropped'] === [NostrRsvpFold::DROP_ATTENDEES_NOT_PUBLIC => 1])
        ->once();
});

it('removes a stored RSVP after its author deletes it, found by the author filter', function () {
    config()->set('services.nostr.relays', [INGEST_RELAY_ONE]);
    $event = ingestPublishedEvent();
    $rsvp = NostrTestEvents::rsvp($event->id, 'accepted', now()->subHour()->timestamp);
    $this->reader->relays = [INGEST_RELAY_ONE => [$rsvp]];
    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();
    expect(MeetupEventNostrRsvp::query()->count())->toBe(1);

    $this->travel(5)->minutes();
    $deletion = NostrTestEvents::deletion([['e', $rsvp['id']]], now()->timestamp);
    $this->reader->relays = [INGEST_RELAY_ONE => [$rsvp, $deletion]];
    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect(MeetupEventNostrRsvp::query()->count())->toBe(0)
        ->and(collect($this->reader->reads[1]['filters'])->firstWhere('kinds', [NostrRsvpFold::KIND_DELETION]))
        ->toMatchArray(['authors' => [NostrTestEvents::pubkey(NostrTestEvents::ATTENDEE_SECRET)]]);
});

it('asks a second time for the deletions of a key it has never stored', function () {
    config()->set('services.nostr.relays', [INGEST_RELAY_ONE]);
    $event = ingestPublishedEvent();
    $rsvp = NostrTestEvents::rsvp($event->id, 'accepted', now()->subMinutes(10)->timestamp);
    $deletion = NostrTestEvents::deletion([['e', $rsvp['id']]], now()->subMinute()->timestamp);
    $this->reader->relays = [INGEST_RELAY_ONE => [$rsvp, $deletion]];

    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect(MeetupEventNostrRsvp::query()->count())->toBe(0)
        ->and($this->reader->reads)->toHaveCount(2)
        ->and($this->reader->reads[1]['filters'])->toBe([[
            'kinds' => [NostrRsvpFold::KIND_DELETION],
            'authors' => [NostrTestEvents::pubkey(NostrTestEvents::ATTENDEE_SECRET)],
            'limit' => IngestNostrRsvps::FILTER_LIMIT,
        ]]);
});

it('treats the relay as unknown when the second read gets no EOSE', function () {
    config()->set('services.nostr.relays', [INGEST_RELAY_ONE]);
    $event = ingestPublishedEvent();
    $rsvp = NostrTestEvents::rsvp($event->id, 'accepted', now()->subMinutes(10)->timestamp);

    $reader = new class extends ScriptedNostrRelayReader
    {
        public function read(string $relayUrl, array $filters): App\Support\NostrRelayReadResult
        {
            return count($this->reads) === 0
                ? parent::read($relayUrl, $filters)
                : tap(App\Support\NostrRelayReadResult::unknown('no EOSE for subscription'), fn () => $this->reads[] = ['relay' => $relayUrl, 'filters' => $filters]);
        }
    };
    $reader->relays = [INGEST_RELAY_ONE => [$rsvp]];
    app()->instance(NostrRelayReader::class, $reader);

    $this->artisan('nostr:ingest-rsvps')->assertFailed();

    expect(MeetupEventNostrRsvp::query()->count())->toBe(0)
        ->and($reader->reads)->toHaveCount(2)
        ->and(Cache::get('nostr:ingest-rsvps:cursor:'.sha1(INGEST_RELAY_ONE)))->toBeNull();
});

it('re-links stored RSVPs when an account links or drops the key, without touching updated_at', function () {
    config()->set('services.nostr.relays', [INGEST_RELAY_ONE]);
    $event = ingestPublishedEvent();
    $row = MeetupEventNostrRsvp::factory()->create([
        'meetup_event_id' => $event->id,
        'pubkey' => NostrTestEvents::pubkey(NostrTestEvents::ATTENDEE_SECRET),
    ]);
    $row->forceFill(['updated_at' => now()->subDay()])->save();
    $storedAt = $row->fresh()->updated_at->timestamp;
    $this->reader->relays = [INGEST_RELAY_ONE => []];

    $older = User::factory()->create(['nostr' => ingestNpubOf(NostrTestEvents::ATTENDEE_SECRET)]);
    User::factory()->create(['nostr' => ingestNpubOf(NostrTestEvents::ATTENDEE_SECRET)]);
    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect($row->fresh())
        ->user_id->toBe($older->id)
        ->and($row->fresh()->updated_at->timestamp)->toBe($storedAt);

    $older->forceFill(['nostr' => null])->save();
    User::query()->where('nostr', ingestNpubOf(NostrTestEvents::ATTENDEE_SECRET))->update(['nostr' => null]);
    $this->artisan('nostr:ingest-rsvps')->assertSuccessful();

    expect($row->fresh()->user_id)->toBeNull();
});

it('fails without a publisher key and reads nothing', function () {
    config()->set('services.nostr.publisher_key', null);
    ingestPublishedEvent();

    $this->artisan('nostr:ingest-rsvps')->assertFailed();

    expect($this->reader->reads)->toBe([]);
});

it('is scheduled every five minutes behind a short overlap lock', function () {
    $entries = array_values(array_filter(
        app(Schedule::class)->events(),
        fn (ScheduledEvent $event): bool => str_contains((string) $event->command, 'nostr:ingest-rsvps'),
    ));

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->expression)->toBe('*/5 * * * *')
        ->and($entries[0]->withoutOverlapping)->toBeTrue()
        ->and($entries[0]->expiresAt)->toBe(10);
});
