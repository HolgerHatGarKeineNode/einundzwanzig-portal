<?php

/*
|--------------------------------------------------------------------------
| Issue #70 — the API serves ALL links of an event, not just the first
|--------------------------------------------------------------------------
|
| Both doors are covered on purpose: MeetupEventResource (store, update,
| mine, mineShow) and the hand-built array of the public list endpoint,
| which does not go through the resource and is therefore the one that
| silently stays behind when a field is added.
|
*/

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function meetupEventLinksApiMeetup(): Meetup
{
    Sanctum::actingAs($user = User::factory()->create());

    return Meetup::factory()->create(['created_by' => $user->id]);
}

it('creates an event with several labelled links and returns all of them', function () {
    $meetup = meetupEventLinksApiMeetup();

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'links' => [
            ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc'],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.links', [
            ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc', 'label' => null],
        ])
        // The deprecated single field keeps answering with the first entry.
        ->assertJsonPath('data.link', 'https://www.meetup.com/bitcoin-berlin/');
});

it('takes the deprecated single link as a one-entry list', function () {
    $meetup = meetupEventLinksApiMeetup();

    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'link' => 'https://example.com/old-client',
    ])
        ->assertCreated()
        ->assertJsonPath('data.link', 'https://example.com/old-client')
        ->assertJsonPath('data.links', [['url' => 'https://example.com/old-client', 'label' => null]]);
});

it('stores the deprecated single link in the list column as well, so a row has one shape', function () {
    $meetup = meetupEventLinksApiMeetup();

    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'link' => 'https://example.com/old-client',
    ])->assertCreated();

    $event = MeetupEvent::query()->latest('id')->firstOrFail();

    // The COLUMN, not the accessor: linkList() would answer correctly through its
    // fallback even if nothing had been written here, and then the day the `link`
    // column is dropped the list would be empty. This is what makes the stored row
    // the truth rather than the mirror.
    expect(json_decode(DB::table('meetup_events')->where('id', $event->id)->value('links'), true))
        ->toBe([['url' => 'https://example.com/old-client']]);
});

it('replaces the whole list on update and can empty it', function () {
    $meetup = meetupEventLinksApiMeetup();
    $event = MeetupEvent::factory()->for($meetup)->create([
        'links' => [['url' => 'https://example.com/one', 'label' => 'One']],
    ]);

    $this->patchJson("/api/meetup-events/{$event->id}", [
        'links' => [['url' => 'https://luma.com/berlin', 'label' => 'Luma']],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [['url' => 'https://luma.com/berlin', 'label' => 'Luma']])
        ->assertJsonPath('data.link', 'https://luma.com/berlin');

    $this->patchJson("/api/meetup-events/{$event->id}", ['links' => []])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [])
        ->assertJsonPath('data.link', null);
});

/*
 * The three shapes `links` can arrive in on a PATCH, one test each (gate finding on
 * 341d3ee). They are three DIFFERENT answers and the middle one is the one that ate an
 * organiser's list: `sometimes` treats an explicitly sent null as PRESENT, so the key
 * reached update() and blanked the column, and linkList() then fell back to the single
 * `link` mirror — five labelled entries collapsed to one bare URL.
 */
function meetupEventWithFiveLinks(Meetup $meetup): MeetupEvent
{
    return MeetupEvent::factory()->for($meetup)->create([
        'links' => [
            ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
            ['url' => 'https://x.com/btc_berlin', 'label' => 'X'],
            ['url' => 'https://berlin.einundzwanzig.space'],
        ],
    ]);
}

it('leaves the list alone when links is absent from the patch', function () {
    $meetup = meetupEventLinksApiMeetup();
    $event = meetupEventWithFiveLinks($meetup);
    $before = DB::table('meetup_events')->where('id', $event->id)->value('links');

    $this->patchJson("/api/meetup-events/{$event->id}", ['description' => 'Neuer Text'])
        ->assertSuccessful()
        ->assertJsonPath('data.links', $event->linkList());

    expect(DB::table('meetup_events')->where('id', $event->id)->value('links'))->toBe($before);
});

it('leaves the list alone when links is sent as null', function () {
    $meetup = meetupEventLinksApiMeetup();
    $event = meetupEventWithFiveLinks($meetup);
    $before = DB::table('meetup_events')->where('id', $event->id)->value('links');

    $this->patchJson("/api/meetup-events/{$event->id}", ['links' => null, 'description' => 'Neuer Text'])
        ->assertSuccessful()
        ->assertJsonPath('data.links', $event->linkList());

    // Byte-identical, not merely "five entries again": the labels are the part a
    // fallback to the `link` mirror would have destroyed without a trace.
    expect(DB::table('meetup_events')->where('id', $event->id)->value('links'))->toBe($before)
        ->and($event->fresh()->linkList())->toHaveCount(5)
        ->and($event->fresh()->linkList()[1])->toBe(['url' => 'https://luma.com/berlin', 'label' => 'Luma']);
});

it('clears the list when links is sent as an empty array', function () {
    $meetup = meetupEventLinksApiMeetup();
    $event = meetupEventWithFiveLinks($meetup);

    $this->patchJson("/api/meetup-events/{$event->id}", ['links' => []])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [])
        ->assertJsonPath('data.link', null);

    expect(json_decode(DB::table('meetup_events')->where('id', $event->id)->value('links'), true))->toBe([]);
});

it('keeps the deprecated single link working as an explicit replacement of the list', function () {
    $meetup = meetupEventLinksApiMeetup();
    $event = meetupEventWithFiveLinks($meetup);

    // `link` sent, `links` sent as null: the legacy field is the only thing the client
    // said anything about, so it wins — one entry, and no leftovers of the old list.
    $this->patchJson("/api/meetup-events/{$event->id}", ['links' => null, 'link' => 'https://example.com/only'])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [['url' => 'https://example.com/only', 'label' => null]])
        ->assertJsonPath('data.link', 'https://example.com/only');
});

it('cannot lose anything when links is null on create, and still honours the legacy field', function () {
    $meetup = meetupEventLinksApiMeetup();

    // POST has no stored list to protect, so the same null is simply "nothing given".
    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'links' => null,
        'link' => 'https://example.com/old-client',
    ])
        ->assertCreated()
        ->assertJsonPath('data.links', [['url' => 'https://example.com/old-client', 'label' => null]]);

    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-02 18:00:00',
        'location' => 'Marktplatz',
        'links' => null,
    ])
        ->assertCreated()
        ->assertJsonPath('data.links', [])
        ->assertJsonPath('data.link', null);
});

it('accepts five links and refuses a sixth with a message', function () {
    $meetup = meetupEventLinksApiMeetup();

    $links = fn (int $count): array => collect(range(1, $count))
        ->map(fn (int $number): array => ['url' => "https://example.com/{$number}"])
        ->all();

    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'links' => $links(5),
    ])->assertCreated();

    $rejected = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'links' => $links(6),
    ]);

    $rejected->assertJsonValidationErrors(['links']);

    // Refused, not trimmed to five: nothing was stored for the second request.
    expect(MeetupEvent::query()->count())->toBe(1)
        ->and($rejected->json('errors.links.0'))->not->toBe('');
});

it('rejects an entry that is not a URL and one without a URL at all', function () {
    $meetup = meetupEventLinksApiMeetup();

    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'links' => [
            ['url' => 'https://example.com/fine'],
            ['url' => 'not-a-url'],
            ['label' => 'Telegram'],
        ],
    ])->assertJsonValidationErrors(['links.1.url', 'links.2.url']);
});

it('rejects a label longer than the column allows', function () {
    $meetup = meetupEventLinksApiMeetup();

    $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'links' => [['url' => 'https://example.com', 'label' => str_repeat('a', 101)]],
    ])->assertJsonValidationErrors(['links.0.label']);
});

it('serves the list on the public month endpoint, which builds its payload by hand', function () {
    $meetup = meetupEventLinksApiMeetup();
    MeetupEvent::factory()->for($meetup)->create([
        'start' => '2026-08-05 18:00:00',
        'links' => [
            ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
            ['url' => 'https://t.me/berlin_btc'],
        ],
    ]);

    $this->getJson('/api/meetup-events')
        ->assertSuccessful()
        ->assertJsonPath('0.links', [
            ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
            ['url' => 'https://t.me/berlin_btc', 'label' => null],
        ])
        ->assertJsonPath('0.link', 'https://www.meetup.com/bitcoin-berlin/');
});

it('gives every occurrence of an API-created series the same list', function () {
    $meetup = meetupEventLinksApiMeetup();

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'links' => [['url' => 'https://luma.com/berlin', 'label' => 'Luma']],
        'recurrence_type' => 'weekly',
        'recurrence_end_date' => '2026-08-15 00:00:00',
    ]);

    $response->assertCreated();

    expect($response->json('data'))->not->toBeEmpty();

    foreach ($response->json('data') as $event) {
        expect($event['links'])->toBe([['url' => 'https://luma.com/berlin', 'label' => 'Luma']]);
    }
});

it('still gives every occurrence of a series created with the deprecated field its link', function () {
    $meetup = meetupEventLinksApiMeetup();

    $response = $this->postJson('/api/meetup-events', [
        'meetup_id' => $meetup->id,
        'start' => '2026-08-01 18:00:00',
        'location' => 'Marktplatz',
        'link' => 'https://example.com/old-client',
        'recurrence_type' => 'weekly',
        'recurrence_end_date' => '2026-08-15 00:00:00',
    ]);

    $response->assertCreated();

    foreach ($response->json('data') as $event) {
        expect($event['link'])->toBe('https://example.com/old-client')
            ->and($event['links'])->toBe([['url' => 'https://example.com/old-client', 'label' => null]]);
    }
});
