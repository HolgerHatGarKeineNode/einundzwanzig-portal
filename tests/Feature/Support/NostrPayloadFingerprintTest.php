<?php

use App\Models\ApiChange;
use App\Models\Meetup;
use App\Support\NostrPayloadFingerprint;
use swentel\nostr\Event\Event;

/**
 * The fingerprint is the whole of the automatic trigger in issue #92, so it is pinned
 * here field by field rather than only through the command that uses it.
 *
 * Both directions of getting it wrong are a defect, and they are opposite defects:
 * cover too little and a changed payload never reaches the back catalogue, which is the
 * bug #92 reports, now behind a mechanism that looks like it works; cover too much — a
 * single clock-bound field is enough — and every record differs on every run and the
 * scheduler broadcasts the entire catalogue to every relay for ever.
 */
function fingerprintEvent(int $kind = 31923, string $content = 'the payload', array $tags = [['d', 'meetup-event-1'], ['title', 'Bitcoin Meetup']]): Event
{
    $event = new Event;
    $event->setKind($kind);
    $event->setContent($content);
    $event->setTags($tags);
    $event->setCreatedAt(1_800_000_000);

    return $event;
}

it('gives the same fingerprint to the same payload', function () {
    expect(NostrPayloadFingerprint::of(fingerprintEvent()))
        ->toBe(NostrPayloadFingerprint::of(fingerprintEvent()))
        ->and(NostrPayloadFingerprint::of(fingerprintEvent()))->toMatch('/^[0-9a-f]{64}$/');
});

/**
 * THE FAILURE MODE THAT DOES NOT STOP. `NostrCalendarEventFactory` stamps `created_at`
 * from the application clock on every build, so a fingerprint that covered it would
 * differ from the stored one on every single run — and the scheduled `--changed` entry
 * would re-send every published record to every relay, hourly, for ever.
 */
it('ignores created_at, which changes on every build of the same record', function () {
    $earlier = fingerprintEvent();
    $earlier->setCreatedAt(1_800_000_000);

    $later = fingerprintEvent();
    $later->setCreatedAt(1_900_000_000);

    expect(NostrPayloadFingerprint::of($later))->toBe(NostrPayloadFingerprint::of($earlier));
});

/**
 * `id` and `sig` are derived from `created_at` (NIP-01: the id is the hash of
 * `[0, pubkey, created_at, kind, tags, content]`, the signature is over the id), so
 * they carry the clock in disguise. `pubkey` is excluded because a key change moves the
 * ADDRESS, not the payload, and both commands refuse to re-send under a foreign key.
 */
it('ignores the signature envelope: id, sig and pubkey', function () {
    $bare = fingerprintEvent();

    $signed = fingerprintEvent();
    $signed->setId(str_repeat('a1', 32));
    $signed->setSignature(str_repeat('f0', 64));
    $signed->setPublicKey(str_repeat('b2', 32));

    expect(NostrPayloadFingerprint::of($signed))->toBe(NostrPayloadFingerprint::of($bare));
});

/**
 * THE FAILURE MODE THAT IS SILENT. Each of these is a real change this portal has
 * shipped: the `t` tags of #69 added tags, the `start_tzid` repair of #104 changed a tag
 * value, and a meetup's `intro` is the event content.
 */
it('changes when any part of the published payload changes', function (string $label, Event $changed) {
    expect(NostrPayloadFingerprint::of($changed))
        ->not->toBe(NostrPayloadFingerprint::of(fingerprintEvent()), $label);
})->with([
    'content' => ['content', fn () => fingerprintEvent(content: 'a different payload')],
    'kind' => ['kind', fn () => fingerprintEvent(kind: 31924)],
    'a tag value' => ['a tag value', fn () => fingerprintEvent(tags: [['d', 'meetup-event-1'], ['title', 'Renamed Meetup']])],
    'an added tag' => ['an added tag', fn () => fingerprintEvent(tags: [['d', 'meetup-event-1'], ['title', 'Bitcoin Meetup'], ['t', 'indianapolis']])],
    'a removed tag' => ['a removed tag', fn () => fingerprintEvent(tags: [['d', 'meetup-event-1']])],
    'tag order' => ['tag order', fn () => fingerprintEvent(tags: [['title', 'Bitcoin Meetup'], ['d', 'meetup-event-1']])],
]);

/**
 * Order is part of the fingerprint because it is part of the event: NIP-01 serialises
 * tags as an ordered array, so two events carrying the same tags in a different order
 * have different ids and are different events on the wire.
 */
it('treats a reordered tag list as a different payload', function () {
    $original = fingerprintEvent(tags: [['d', 'x'], ['t', 'bitcoin'], ['t', 'meetup']]);
    $reordered = fingerprintEvent(tags: [['d', 'x'], ['t', 'meetup'], ['t', 'bitcoin']]);

    expect(NostrPayloadFingerprint::of($reordered))->not->toBe(NostrPayloadFingerprint::of($original));
});

it('reports a record with no stored fingerprint as stale', function () {
    $meetup = Meetup::factory()->create();

    expect($meetup->nostr_payload_hash)->toBeNull()
        ->and(NostrPayloadFingerprint::isStale($meetup, fingerprintEvent()))->toBeTrue();
});

it('reports a record as fresh only while the stored fingerprint matches', function () {
    $meetup = Meetup::factory()->create();
    $event = fingerprintEvent();

    NostrPayloadFingerprint::remember($meetup, $event);

    expect(NostrPayloadFingerprint::isStale($meetup, $event))->toBeFalse()
        ->and(NostrPayloadFingerprint::isStale($meetup->fresh(), $event))->toBeFalse()
        ->and(NostrPayloadFingerprint::isStale($meetup, fingerprintEvent(content: 'moved')))->toBeTrue();
});

/**
 * A transmission is not a change to the record. `updated_at` is what other code reads to
 * answer "when did this last change", and it is also the signal a naive version of this
 * feature would have keyed on — so moving it here would corrupt the very field whose
 * unsuitability is the reason the fingerprint exists.
 */
it('records the fingerprint without moving updated_at', function () {
    $meetup = Meetup::factory()->create(['updated_at' => now()->subMonth()]);
    $before = $meetup->fresh()->updated_at;

    $this->travel(1)->hour();
    NostrPayloadFingerprint::remember($meetup, fingerprintEvent());

    expect($meetup->fresh()->updated_at->equalTo($before))->toBeTrue()
        ->and($meetup->fresh()->nostr_payload_hash)->toBe(NostrPayloadFingerprint::of(fingerprintEvent()));
});

/**
 * Both models carry `ApiChangeObserver`, so an ordinary save here would publish "this
 * meetup changed" to the change log, the broadcast channel and every webhook subscriber
 * — every time a calendar is refreshed, with no field a consumer could point at.
 */
it('records the fingerprint without writing to the public change log', function () {
    config()->set('einundzwanzig.change_log.enabled', true);
    $meetup = Meetup::factory()->create();
    $before = ApiChange::query()->count();

    NostrPayloadFingerprint::remember($meetup, fingerprintEvent());

    expect(ApiChange::query()->count())->toBe($before);

    // Positive control: the log really is on, so the count above is a real absence
    // rather than a feature switched off.
    $meetup->update(['name' => 'Renamed for the control']);
    expect(ApiChange::query()->count())->toBeGreaterThan($before);
});

/**
 * A `save()` would flush whatever else happens to be dirty on the model. This statement
 * carries one column, so an in-memory edit nobody asked to persist stays unpersisted.
 */
it('writes only its own column', function () {
    $meetup = Meetup::factory()->create(['name' => 'Stored Name']);
    $meetup->name = 'Never Saved';

    NostrPayloadFingerprint::remember($meetup, fingerprintEvent());

    expect($meetup->fresh()->name)->toBe('Stored Name');
});
