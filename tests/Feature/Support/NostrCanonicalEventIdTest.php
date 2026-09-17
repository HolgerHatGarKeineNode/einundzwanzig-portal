<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Support\NostrCalendarEventFactory;
use Illuminate\Support\Facades\DB;
use swentel\nostr\Event\Event;
use swentel\nostr\Sign\Sign;

/*
 * A published calendar event is only publishable if its id is the one a relay computes.
 *
 * NIP-01 fixes the id as the SHA-256 of `[0, pubkey, created_at, kind, tags, content]`
 * serialised with the characters of the content included verbatim — only `"`, `\`, `\n`,
 * `\r`, `\t`, `\b` and `\f` are escaped. `swentel\nostr\Sign\Sign::serializeEvent()`
 * produces that string with `json_encode(..., JSON_UNESCAPED_SLASHES |
 * JSON_UNESCAPED_UNICODE)` — which is right for every character but two: without
 * JSON_UNESCAPED_LINE_TERMINATORS, PHP writes U+2028 and U+2029 as `\u2028`/`\u2029`.
 * The event then carries an id over a byte string no relay reconstructs, `nak verify`
 * reports `invalid .id`, and the relays reject it.
 *
 * The first test below is the control that keeps the rest honest: it pins the library
 * defect itself, so a future release that fixes it turns this file red instead of
 * letting the assertions pass for a reason that no longer exists.
 */

const CANONICAL_ID_TEST_KEY = '4f964f6b93a5b1e5f6f9b1d3a4f5e6d7c8b9a0f1e2d3c4b5a6978869504132a1';

/**
 * The NIP-01 id of a signed event, recomputed independently of the library — same six
 * fields, plus the one JSON flag the library is missing.
 */
function canonicalNip01Id(Event $event): string
{
    return hash('sha256', json_encode(
        [0, $event->getPublicKey(), $event->getCreatedAt(), $event->getKind(), $event->getTags(), $event->getContent()],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR,
    ));
}

function canonicalIdMeetup(array $attributes = []): Meetup
{
    $city = City::factory()->create([
        'country_id' => Country::factory()->create(['code' => 'de'])->id,
        'latitude' => 52.5200,
        'longitude' => 13.4050,
    ]);

    return Meetup::factory()->create(array_merge(['city_id' => $city->id], $attributes));
}

it('measures that the library signs U+2028 and U+2029 with a non-canonical id', function (string $separator) {
    $event = new Event;
    $event->setKind(1)->setCreatedAt(1758000000)->setTags([])->setContent("Zeile eins{$separator}Zeile zwei");

    (new Sign)->signEvent($event, CANONICAL_ID_TEST_KEY);

    expect($event->getId())->not->toBe(canonicalNip01Id($event));
})->with([
    'U+2028 LINE SEPARATOR' => ["\u{2028}"],
    'U+2029 PARAGRAPH SEPARATOR' => ["\u{2029}"],
]);

it('signs ordinary text with a canonical id', function () {
    $event = new Event;
    $event->setKind(1)->setCreatedAt(1758000000)->setTags([])->setContent("Zeile eins\nZeile zwei — mit Umlaut, ⚡ und https://example.com/a/b");

    (new Sign)->signEvent($event, CANONICAL_ID_TEST_KEY);

    expect($event->getId())->toBe(canonicalNip01Id($event));
});

it('builds a canonical id for a meetup event whose stored description holds U+2028', function () {
    $meetup = canonicalIdMeetup(['nostr_publishing_enabled' => true]);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
    ]);

    // Past the model on purpose: TextNormalizer cleans what is SAVED, and the rows that
    // block the queue today were written before it did. This is what one of them looks
    // like.
    DB::table('meetup_events')
        ->where('id', $meetupEvent->id)
        ->update(['description' => "Wir treffen uns\u{2028}jeden Dienstag."]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent->fresh(), str_repeat('a1', 32));

    (new Sign)->signEvent($event, CANONICAL_ID_TEST_KEY);

    expect($event->getContent())->toBe("Wir treffen uns\njeden Dienstag.")
        ->and($event->getId())->toBe(canonicalNip01Id($event));
});

it('builds a canonical id for a meetup whose stored name and intro hold U+2029', function () {
    $meetup = canonicalIdMeetup(['nostr_publishing_enabled' => true]);

    DB::table('meetups')
        ->where('id', $meetup->id)
        ->update([
            'name' => "Einundzwanzig\u{2029}Berlin",
            'intro' => "Erster Absatz\u{2029}Zweiter Absatz",
        ]);

    $event = NostrCalendarEventFactory::forMeetup($meetup->fresh());

    (new Sign)->signEvent($event, CANONICAL_ID_TEST_KEY);

    // The tags matter as much as the content: the title is part of the same serialised
    // array, so one separator in a meetup name breaks the id just as thoroughly.
    expect($event->getTag('title'))->toBe([['title', "Einundzwanzig\nBerlin"]])
        ->and($event->getContent())->toBe("Erster Absatz\nZweiter Absatz")
        ->and($event->getId())->toBe(canonicalNip01Id($event));
});

/*
 * The sanitising pass rebuilds the event instead of writing the cleaned tags back onto
 * it, because `Event::setTags()` APPENDS rather than replaces (1.9.4). Written back, the
 * tag list doubles — and nothing else notices: the id is computed over whatever is in the
 * list, so the event stays internally consistent and the relays take a calendar entry
 * with every tag twice. This test is what makes that visible.
 */
it('leaves a clean payload with exactly one of each single-valued tag', function () {
    $meetup = canonicalIdMeetup(['name' => 'Einundzwanzig Berlin', 'nostr_publishing_enabled' => true]);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'title' => 'Stammtisch',
    ]);

    $event = NostrCalendarEventFactory::forMeetupEvent($meetupEvent, str_repeat('a1', 32));
    $calendar = NostrCalendarEventFactory::forMeetup($meetup);

    expect($event->getTag('d'))->toHaveCount(1)
        ->and($event->getTag('title'))->toHaveCount(1)
        ->and($event->getTag('start'))->toHaveCount(1)
        ->and($event->getTag('a'))->toHaveCount(1)
        ->and($calendar->getTag('d'))->toHaveCount(1)
        ->and($calendar->getTag('title'))->toHaveCount(1);
});

it('keeps a description that an organiser saves free of U+2028', function () {
    $meetup = canonicalIdMeetup(['nostr_publishing_enabled' => true]);
    $meetupEvent = MeetupEvent::factory()->create([
        'meetup_id' => $meetup->id,
        'start' => now()->addWeek(),
        'description' => "Aus dem Textverarbeitungsprogramm kopiert:\u{2028}mit Trennzeichen.",
    ]);

    expect($meetupEvent->fresh()->description)
        ->toBe("Aus dem Textverarbeitungsprogramm kopiert:\nmit Trennzeichen.");
});
