<?php

use App\Support\NostrEventVerifier;
use App\Support\NostrRsvpFold;
use App\Support\NostrRsvpTarget;
use swentel\nostr\Event\Event;
use Tests\Fixtures\NostrTestEvents;

/*
|--------------------------------------------------------------------------
| Hybrid RSVP (D12): validation and fold of kind 31925 / kind 5, without a database
|--------------------------------------------------------------------------
|
| Every test asserts the DROP REASON, not only that nothing was stored. Two rules can
| reject the same event (a foreign coordinate is also absent from the target map), and a
| test that only checks "not stored" stays green when the rule it is named after is
| deleted.
*/

const RSVP_FOLD_NOW = 1789700000;

function rsvpFoldTarget(int $meetupEventId = 1, array $overrides = []): NostrRsvpTarget
{
    return new NostrRsvpTarget(...array_merge([
        'meetupEventId' => $meetupEventId,
        'cancelled' => false,
        'rsvpEnabled' => true,
        'attendeesPublic' => true,
        'inWindow' => true,
    ], $overrides));
}

/**
 * @param  list<array<string, mixed>>  $events
 * @param  array<string, NostrRsvpTarget>|null  $targets
 * @param  array<string, array{nostr_event_id: string, d_tag: string, rsvp_created_at: int}>  $stored
 */
function rsvpFold(array $events, ?array $targets = null, array $stored = []): App\Support\NostrRsvpFoldResult
{
    return NostrRsvpFold::fold(
        $events,
        [NostrTestEvents::pubkey(NostrTestEvents::PUBLISHER_SECRET)],
        $targets ?? [NostrTestEvents::coordinate(1) => rsvpFoldTarget()],
        $stored,
        RSVP_FOLD_NOW,
    );
}

function rsvpFoldStoredKey(int $meetupEventId = 1, string $secret = NostrTestEvents::ATTENDEE_SECRET): string
{
    return $meetupEventId.':'.NostrTestEvents::pubkey($secret);
}

it('stores a valid RSVP to a published event as the row the ingest writes', function () {
    $rsvp = NostrTestEvents::rsvp(1, 'tentative', RSVP_FOLD_NOW - 60, dTag: 'my-rsvp');

    $result = rsvpFold([$rsvp]);

    expect($result->upserts)->toBe([[
        'meetup_event_id' => 1,
        'pubkey' => NostrTestEvents::pubkey(NostrTestEvents::ATTENDEE_SECRET),
        'nostr_event_id' => $rsvp['id'],
        'd_tag' => 'my-rsvp',
        'status' => 'tentative',
        'rsvp_created_at' => RSVP_FOLD_NOW - 60,
    ]])
        ->and($result->deletions)->toBe([])
        ->and($result->dropped)->toBe([]);
});

it('accepts an RSVP whose content holds U+2028, which swentel rejects', function () {
    $rsvp = NostrTestEvents::u2028Rsvp();

    // The control: the library check the ingest deliberately does not use drops it.
    expect(bin2hex($rsvp['content']))->toContain('e280a8')
        ->and((new Event)->verify(json_encode($rsvp)))->toBeFalse()
        ->and(NostrEventVerifier::verify($rsvp))->toBeTrue();

    $result = rsvpFold([$rsvp]);

    expect($result->upserts)->toHaveCount(1)
        ->and($result->upserts[0]['nostr_event_id'])->toBe($rsvp['id'])
        ->and($result->dropped)->toBe([]);
});

it('drops an RSVP whose signed content was altered', function () {
    $rsvp = NostrTestEvents::rsvp(1, 'declined', RSVP_FOLD_NOW - 60);
    $rsvp['tags'][2] = ['status', 'accepted'];

    expect(rsvpFold([$rsvp]))
        ->upserts->toBe([])
        ->dropped->toBe([NostrRsvpFold::DROP_INVALID_SIGNATURE => 1]);
});

it('drops an RSVP to the same d tag under a foreign publisher key', function () {
    $foreign = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 60, coordinate: NostrTestEvents::coordinate(1, NostrTestEvents::FOREIGN_PUBLISHER_SECRET));

    expect(rsvpFold([$foreign]))
        ->upserts->toBe([])
        ->dropped->toBe([NostrRsvpFold::DROP_FOREIGN_COORDINATE => 1]);
});

it('drops spoofed a tags that only look like a portal event', function (string $coordinate) {
    $spoofed = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 60, coordinate: $coordinate);

    expect(rsvpFold([$spoofed]))
        ->upserts->toBe([])
        ->dropped->toBe([NostrRsvpFold::DROP_FOREIGN_COORDINATE => 1]);
})->with([
    'the calendar kind instead of the event kind' => fn () => str_replace('31923:', '31924:', NostrTestEvents::coordinate(1)),
    'an uppercase pubkey' => fn () => '31923:'.strtoupper(NostrTestEvents::pubkey(NostrTestEvents::PUBLISHER_SECRET)).':meetup-event-1',
    'no d tag at all' => fn () => '31923:'.NostrTestEvents::pubkey(NostrTestEvents::PUBLISHER_SECRET).':',
]);

it('drops an RSVP to a publisher coordinate the portal never published', function () {
    $unknown = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 60, coordinate: NostrTestEvents::coordinate(999));

    expect(rsvpFold([$unknown]))
        ->upserts->toBe([])
        ->dropped->toBe([NostrRsvpFold::DROP_UNKNOWN_EVENT => 1]);
});

it('drops an RSVP that names a second, foreign event next to ours', function () {
    $twoTargets = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 60, extraTags: [
        ['a', NostrTestEvents::coordinate(1, NostrTestEvents::FOREIGN_PUBLISHER_SECRET)],
    ]);

    expect(rsvpFold([$twoTargets]))
        ->upserts->toBe([])
        ->dropped->toBe([NostrRsvpFold::DROP_AMBIGUOUS_COORDINATE => 1]);
});

it('drops an RSVP without an a tag', function () {
    $bare = NostrTestEvents::signed(NostrTestEvents::ATTENDEE_SECRET, 31925, [['d', 'x'], ['status', 'accepted']], RSVP_FOLD_NOW - 60);

    expect(rsvpFold([$bare]))->dropped->toBe([NostrRsvpFold::DROP_MISSING_COORDINATE => 1]);
});

it('accepts created_at up to 900 s in the future and drops anything later', function () {
    $atLimit = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW + 900);
    $pastLimit = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW + 901, secret: NostrTestEvents::OTHER_ATTENDEE_SECRET);

    $result = rsvpFold([$atLimit, $pastLimit]);

    expect(array_column($result->upserts, 'nostr_event_id'))->toBe([$atLimit['id']])
        ->and($result->dropped)->toBe([NostrRsvpFold::DROP_FUTURE_CREATED_AT => 1]);
});

it('drops an RSVP whose status is not one of the three NIP-52 values', function (array $statusTags) {
    $rsvp = NostrTestEvents::signed(NostrTestEvents::ATTENDEE_SECRET, 31925, [
        ['a', NostrTestEvents::coordinate(1)],
        ['d', 'x'],
        ...$statusTags,
    ], RSVP_FOLD_NOW - 60);

    expect(rsvpFold([$rsvp]))
        ->upserts->toBe([])
        ->dropped->toBe([NostrRsvpFold::DROP_INVALID_STATUS => 1]);
})->with([
    'unknown word' => [[['status', 'going']]],
    'wrong case' => [[['status', 'Accepted']]],
    'missing' => [[]],
    'two contradicting values' => [[['status', 'accepted'], ['status', 'declined']]],
]);

it('drops RSVPs the meetup event does not accept, each with its own reason', function (array $flags, string $reason) {
    $rsvp = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 60);

    expect(rsvpFold([$rsvp], [NostrTestEvents::coordinate(1) => rsvpFoldTarget(1, $flags)]))
        ->upserts->toBe([])
        ->dropped->toBe([$reason => 1]);
})->with([
    'cancelled event' => [['cancelled' => true], NostrRsvpFold::DROP_EVENT_CANCELLED],
    'attendees not public' => [['attendeesPublic' => false], NostrRsvpFold::DROP_ATTENDEES_NOT_PUBLIC],
    'RSVP disabled' => [['rsvpEnabled' => false], NostrRsvpFold::DROP_RSVP_DISABLED],
    'event long past' => [['inWindow' => false], NostrRsvpFold::DROP_EVENT_OUT_OF_WINDOW],
]);

it('keeps the newest RSVP of a key across d tags and relays', function () {
    $older = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 600, dTag: 'phone');
    $newer = NostrTestEvents::rsvp(1, 'declined', RSVP_FOLD_NOW - 60, dTag: 'laptop');

    // The newer one twice, as two relays would deliver it.
    $result = rsvpFold([$newer, $older, $newer]);

    expect($result->upserts)->toHaveCount(1)
        ->and($result->upserts[0])->toMatchArray(['nostr_event_id' => $newer['id'], 'status' => 'declined', 'd_tag' => 'laptop'])
        ->and($result->dropped)->toBe([]);
});

it('breaks a created_at tie towards the lowest event id', function () {
    $first = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 60, dTag: 'a');
    $second = NostrTestEvents::rsvp(1, 'tentative', RSVP_FOLD_NOW - 60, dTag: 'b');
    $lowest = strcmp($first['id'], $second['id']) < 0 ? $first : $second;

    expect(rsvpFold([$first, $second])->upserts[0]['nostr_event_id'])->toBe($lowest['id'])
        ->and(rsvpFold([$second, $first])->upserts[0]['nostr_event_id'])->toBe($lowest['id']);
});

it('replaces a stored RSVP with a newer one and never with an older one', function () {
    $stored = [rsvpFoldStoredKey() => ['nostr_event_id' => str_repeat('b', 64), 'd_tag' => 'rsvp-1', 'rsvp_created_at' => RSVP_FOLD_NOW - 300]];

    $newer = NostrTestEvents::rsvp(1, 'declined', RSVP_FOLD_NOW - 60);
    $older = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 900, dTag: 'late-arrival');

    expect(rsvpFold([$newer], stored: $stored)->upserts)->toHaveCount(1)
        ->and(rsvpFold([$older], stored: $stored)->upserts)->toBe([])
        ->and(rsvpFold([$older], stored: $stored)->deletions)->toBe([]);
});

it('honours a kind 5 by event id from the RSVP author', function () {
    $rsvp = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 600);
    $deletion = NostrTestEvents::deletion([['e', $rsvp['id']]], RSVP_FOLD_NOW - 60);

    expect(rsvpFold([$rsvp, $deletion]))
        ->upserts->toBe([])
        ->dropped->toBe([NostrRsvpFold::DROP_DELETED => 1]);
});

it('ignores a kind 5 by a foreign key, by event id and by address', function () {
    $rsvp = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 600);
    $attendee = NostrTestEvents::pubkey(NostrTestEvents::ATTENDEE_SECRET);
    $foreignDeletion = NostrTestEvents::deletion([
        ['e', $rsvp['id']],
        ['a', "31925:{$attendee}:rsvp-1"],
    ], RSVP_FOLD_NOW - 60, NostrTestEvents::OTHER_ATTENDEE_SECRET);

    $stored = [rsvpFoldStoredKey() => ['nostr_event_id' => $rsvp['id'], 'd_tag' => 'rsvp-1', 'rsvp_created_at' => RSVP_FOLD_NOW - 600]];

    expect(rsvpFold([$rsvp, $foreignDeletion]))
        ->upserts->toHaveCount(1)
        ->dropped->toBe([])
        ->and(rsvpFold([$foreignDeletion], stored: $stored))
        ->deletions->toBe([]);
});

it('honours a kind 5 by address for every version up to its own time, not after', function () {
    $attendee = NostrTestEvents::pubkey(NostrTestEvents::ATTENDEE_SECRET);
    $deletion = NostrTestEvents::deletion([['a', "31925:{$attendee}:rsvp-1"]], RSVP_FOLD_NOW - 300);

    $before = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 600);
    $sameSecond = NostrTestEvents::rsvp(1, 'tentative', RSVP_FOLD_NOW - 300);
    $after = NostrTestEvents::rsvp(1, 'declined', RSVP_FOLD_NOW - 60);

    expect(rsvpFold([$before, $sameSecond, $deletion]))
        ->upserts->toBe([])
        ->dropped->toBe([NostrRsvpFold::DROP_DELETED => 2])
        ->and(rsvpFold([$before, $after, $deletion])->upserts)->toHaveCount(1)
        ->and(rsvpFold([$before, $after, $deletion])->upserts[0]['nostr_event_id'])->toBe($after['id']);
});

it('removes a stored row its author deleted, unless a newer RSVP replaces it', function () {
    $storedId = str_repeat('c', 64);
    $stored = [rsvpFoldStoredKey() => ['nostr_event_id' => $storedId, 'd_tag' => 'rsvp-1', 'rsvp_created_at' => RSVP_FOLD_NOW - 600]];
    $deletion = NostrTestEvents::deletion([['e', $storedId]], RSVP_FOLD_NOW - 300);

    expect(rsvpFold([$deletion], stored: $stored))
        ->upserts->toBe([])
        ->deletions->toBe([['meetup_event_id' => 1, 'pubkey' => NostrTestEvents::pubkey(NostrTestEvents::ATTENDEE_SECRET)]]);

    // An OLDER RSVP of another d tag becomes the answer once the stored one is gone.
    $fallback = NostrTestEvents::rsvp(1, 'tentative', RSVP_FOLD_NOW - 900, dTag: 'older-device');

    expect(rsvpFold([$deletion, $fallback], stored: $stored))
        ->deletions->toBe([])
        ->upserts->toHaveCount(1)
        ->and(rsvpFold([$deletion, $fallback], stored: $stored)->upserts[0]['nostr_event_id'])->toBe($fallback['id']);
});

it('does not re-verify the stored winner when a relay returns it again', function () {
    $rsvp = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 600);
    $stored = [rsvpFoldStoredKey() => ['nostr_event_id' => $rsvp['id'], 'd_tag' => 'rsvp-1', 'rsvp_created_at' => RSVP_FOLD_NOW - 600]];

    // Same id, broken signature: were it verified, it would be counted as a drop.
    $rsvp['sig'] = str_repeat('0', 128);

    expect(rsvpFold([$rsvp], stored: $stored))
        ->upserts->toBe([])
        ->deletions->toBe([])
        ->dropped->toBe([]);
});

it('drops events of kinds the ingest did not ask for', function () {
    $note = NostrTestEvents::signed(NostrTestEvents::ATTENDEE_SECRET, 1, [['a', NostrTestEvents::coordinate(1)]], RSVP_FOLD_NOW - 60, 'hi');

    expect(rsvpFold([$note]))->dropped->toBe([NostrRsvpFold::DROP_UNEXPECTED_KIND => 1]);
});

it('lists only publisher coordinates as worth resolving', function () {
    $ours = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 60);
    $foreign = NostrTestEvents::rsvp(1, 'accepted', RSVP_FOLD_NOW - 60, coordinate: NostrTestEvents::coordinate(2, NostrTestEvents::FOREIGN_PUBLISHER_SECRET));

    expect(NostrRsvpFold::referencedCoordinates([$ours, $foreign], [NostrTestEvents::pubkey(NostrTestEvents::PUBLISHER_SECRET)]))
        ->toBe([NostrTestEvents::coordinate(1)]);
});
