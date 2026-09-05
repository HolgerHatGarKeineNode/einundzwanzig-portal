<?php

/*
|--------------------------------------------------------------------------
| Issue #108 — the deprecated `link` addresses the FIRST entry, nothing else
|--------------------------------------------------------------------------
|
| Same harm class as #70, one field over: `PATCH {"link": null}` replaced the
| whole stored list with an empty one, so an event with five labelled links
| came back with none. #70 closed that door for `links`; this one closes it
| for `link`.
|
| The rule these tests pin down: `link` is a view of entry ONE of the list.
| Writing it replaces that entry, clearing it removes that entry, and entries
| two to five are never touched either way. `links` keeps replacing the whole
| list — the two fields say different things and must not be conflated.
|
| One test per input shape, exactly as #70 has for `links`, because the shapes
| are three different answers and only a test per shape can tell them apart.
|
*/

use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

function deprecatedLinkApiMeetup(): Meetup
{
    Sanctum::actingAs($user = User::factory()->create());

    return Meetup::factory()->create(['created_by' => $user->id]);
}

/**
 * @param  list<array{url: string, label?: string}>  $links
 */
function eventWithLinks(Meetup $meetup, array $links): MeetupEvent
{
    return MeetupEvent::factory()->for($meetup)->create(['links' => $links]);
}

/**
 * The three labelled links of the issue report, plus the two that make "entries
 * 2 to 5 keep their order and labels" a statement about more than one row.
 *
 * @return list<array{url: string, label?: string}>
 */
function fiveLabelledLinks(): array
{
    return [
        ['url' => 'https://www.meetup.com/bitcoin-berlin/', 'label' => 'Meetup.com'],
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
        ['url' => 'https://x.com/btc_berlin', 'label' => 'X'],
        ['url' => 'https://berlin.einundzwanzig.space'],
    ];
}

/** @return list<array{url: string, label: string|null}> */
function linksInColumn(MeetupEvent $event): array
{
    return json_decode(DB::table('meetup_events')->where('id', $event->id)->value('links'), true);
}

it('leaves the list alone when link is absent from the patch', function () {
    $event = eventWithLinks(deprecatedLinkApiMeetup(), fiveLabelledLinks());
    $before = DB::table('meetup_events')->where('id', $event->id)->value('links');

    $this->patchJson("/api/meetup-events/{$event->id}", ['description' => 'Neuer Text'])
        ->assertSuccessful()
        ->assertJsonPath('data.links', $event->linkList())
        ->assertJsonPath('data.link', 'https://www.meetup.com/bitcoin-berlin/');

    expect(DB::table('meetup_events')->where('id', $event->id)->value('links'))->toBe($before);
});

it('removes only the first entry when link is sent as null', function () {
    $event = eventWithLinks(deprecatedLinkApiMeetup(), fiveLabelledLinks());

    $response = $this->patchJson("/api/meetup-events/{$event->id}", ['link' => null]);

    // The four survivors, in their order, with their labels — the part the old
    // behaviour destroyed without a trace.
    $response->assertSuccessful()
        ->assertJsonPath('data.links', [
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
            ['url' => 'https://x.com/btc_berlin', 'label' => 'X'],
            ['url' => 'https://berlin.einundzwanzig.space', 'label' => null],
        ])
        // `link` is the first entry of the list, and the first entry is now Luma.
        // Answering null here would leave a legacy reader believing an event with
        // four links has none.
        ->assertJsonPath('data.link', 'https://luma.com/berlin');

    expect(linksInColumn($event))->toBe([
        ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
        ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
        ['url' => 'https://x.com/btc_berlin', 'label' => 'X'],
        ['url' => 'https://berlin.einundzwanzig.space'],
    ]);
});

it('replaces only the first entry when link is sent as a URL', function () {
    $event = eventWithLinks(deprecatedLinkApiMeetup(), fiveLabelledLinks());

    $this->patchJson("/api/meetup-events/{$event->id}", ['link' => 'https://example.com/new-first'])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [
            // The label went with the URL it described: an old label on a new link
            // would say something about a link that is no longer there.
            ['url' => 'https://example.com/new-first', 'label' => null],
            ['url' => 'https://luma.com/berlin', 'label' => 'Luma'],
            ['url' => 'https://t.me/berlin_btc', 'label' => 'Telegram'],
            ['url' => 'https://x.com/btc_berlin', 'label' => 'X'],
            ['url' => 'https://berlin.einundzwanzig.space', 'label' => null],
        ])
        ->assertJsonPath('data.link', 'https://example.com/new-first');

    expect(linksInColumn($event))->toHaveCount(5);
});

it('still replaces the whole list when links is sent', function () {
    $event = eventWithLinks(deprecatedLinkApiMeetup(), fiveLabelledLinks());

    $this->patchJson("/api/meetup-events/{$event->id}", [
        'links' => [['url' => 'https://example.com/only', 'label' => 'Only']],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [['url' => 'https://example.com/only', 'label' => 'Only']])
        ->assertJsonPath('data.link', 'https://example.com/only');

    expect(linksInColumn($event))->toBe([['url' => 'https://example.com/only', 'label' => 'Only']]);
});

/*
 * The two edge cases a caller hits without meaning to (issue #108). Both are the
 * same rule applied to a shorter list, which is why they are written down: an
 * empty list has no entry one to remove, and a one-entry list is entirely entry
 * one — so there the new rule and the pre-#70 behaviour coincide exactly, and a
 * legacy client that only ever knew a single link keeps the semantics it had.
 */
it('does nothing when link is cleared on an event that has no links at all', function () {
    $event = eventWithLinks(deprecatedLinkApiMeetup(), []);

    $this->patchJson("/api/meetup-events/{$event->id}", ['link' => null])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [])
        ->assertJsonPath('data.link', null);

    expect(linksInColumn($event))->toBe([]);
});

it('empties the list when link is cleared on an event that has exactly one', function () {
    $event = eventWithLinks(deprecatedLinkApiMeetup(), [
        ['url' => 'https://example.com/only', 'label' => 'Only'],
    ]);

    $this->patchJson("/api/meetup-events/{$event->id}", ['link' => null])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [])
        ->assertJsonPath('data.link', null);

    expect(linksInColumn($event))->toBe([]);
});

it('sets the single link of an event that has none without inventing a second entry', function () {
    $event = eventWithLinks(deprecatedLinkApiMeetup(), []);

    $this->patchJson("/api/meetup-events/{$event->id}", ['link' => 'https://example.com/first'])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [['url' => 'https://example.com/first', 'label' => null]])
        ->assertJsonPath('data.link', 'https://example.com/first');
});

it('applies the same rule to a row the backfill never reached, where links is still NULL', function () {
    $meetup = deprecatedLinkApiMeetup();
    $event = MeetupEvent::factory()->for($meetup)->create(['link' => 'https://example.com/legacy']);

    // A pre-#70 row: the column is genuinely NULL, and its only link is the
    // deprecated field, which IS entry one.
    DB::table('meetup_events')->where('id', $event->id)->update(['links' => null]);

    $this->patchJson("/api/meetup-events/{$event->id}", ['link' => null])
        ->assertSuccessful()
        ->assertJsonPath('data.links', [])
        ->assertJsonPath('data.link', null);

    expect(linksInColumn($event))->toBe([]);
});
