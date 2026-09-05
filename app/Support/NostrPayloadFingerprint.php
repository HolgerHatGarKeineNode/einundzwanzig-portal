<?php

namespace App\Support;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use swentel\nostr\Event\Event;

/**
 * The fingerprint of a published NIP-52 payload, and the record of the last one sent.
 *
 * This is the trigger issue #92 asked for. `nostr_coordinate` says WHERE a record sits
 * on the relays; it cannot say whether what sits there is still what the portal would
 * publish today. Without that second answer, a change to the published payload — the
 * geography `t` tags of #69, the `start_tzid` repair of #104 — reaches the back
 * catalogue only when a human remembers to run `nostr:republish-calendar`.
 *
 * ## What the fingerprint covers, and why exactly this
 *
 * KIND, TAGS AND CONTENT, in that order, hashed with SHA-256. Those three are the whole
 * of what a reader of the event sees, and they are precisely the fields a change to the
 * portal's code or data can move. The order and the shape mirror NIP-01's own
 * serialisation for the event id — `[0, pubkey, created_at, kind, tags, content]` — with
 * the two clock- and key-bound fields removed, so the value is recognisable as "the
 * NIP-01 id of everything that is not the clock or the key".
 *
 * `created_at` IS EXCLUDED, and this is the single most load-bearing decision in the
 * class. {@see NostrCalendarEventFactory} stamps it from the application clock at build
 * time, so it differs on every build of the same record. Including it would make every
 * record's fingerprint differ from the stored one on every run — the automatic path
 * would re-send the entire catalogue to every relay, every run, forever, and unlike a
 * one-off bulk repair it would not stop. `NostrPayloadFingerprintTest` pins that
 * exclusion directly rather than trusting this paragraph.
 *
 * `id` and `sig` ARE EXCLUDED for the same reason once removed: `id` is the hash of the
 * six NIP-01 fields including `created_at`, and `sig` is over `id`, so both inherit the
 * clock. They are also only present after signing, while this fingerprint is taken of
 * the unsigned event the factory built.
 *
 * `pubkey` IS EXCLUDED because a key change is not a payload change, it is an ADDRESS
 * change: the coordinate `<kind>:<pubkey>:<d>` moves, and both commands already refuse
 * to re-send a record whose stored coordinate names a different key. Hashing the pubkey
 * in as well would duplicate that check in the one place where it reads as a payload
 * repair rather than as the key rotation it is.
 *
 * TAG ORDER IS PART OF THE FINGERPRINT, deliberately. NIP-01 serialises tags as an
 * ordered array, so two events with the same tags in a different order are two different
 * events on the wire, with different ids. The factory builds every list deterministically
 * (`a` tags ordered by `start` then `id`, topics most-specific first), so an order change
 * is a real change and should trigger a re-send.
 *
 * ## What is stored, and what NULL means
 *
 * The fingerprint of the last event SUCCESSFULLY TRANSMITTED for the record, in
 * `nostr_payload_hash`. NULL means unknown — never published, or published before the
 * column existed. A record with a coordinate and a NULL fingerprint is therefore stale
 * by {@see self::isStale()}, which is what carries the #69/#104 back catalogue into the
 * repair instead of silently declaring it up to date. Nothing is backfilled; see the
 * migration `2026_09_05_191105_add_nostr_payload_hash_...` for that decision in full.
 */
final class NostrPayloadFingerprint
{
    /**
     * The column both models carry it in. Named once so a rename cannot half-happen.
     */
    public const COLUMN = 'nostr_payload_hash';

    /**
     * The fingerprint of an event's payload.
     *
     * JSON_UNESCAPED_SLASHES and JSON_UNESCAPED_UNICODE match `Event::toJson()`, so the
     * bytes hashed here are the bytes that go on the wire for these three fields. They
     * do not change the VALUE of the comparison — any consistent encoding would do —
     * but a fingerprint that is computed over a different byte string than the one an
     * operator sees in `-v` output is a needless second reality.
     */
    public static function of(Event $event): string
    {
        return hash('sha256', json_encode(
            [$event->getKind(), $event->getTags(), $event->getContent()],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * Whether the relays hold something other than what this event would put there.
     *
     * A non-string stored value (NULL, and nothing else can occur) counts as stale. That
     * is the "unknown means presumed stale" rule stated in the class docblock: the safe
     * direction is one idempotent re-send, because the unsafe direction is the defect
     * #92 reports, dressed up as a mechanism that looks like it works.
     */
    public static function isStale(Meetup|MeetupEvent $record, Event $event): bool
    {
        $stored = $record->getAttribute(self::COLUMN);

        return ! is_string($stored) || ! hash_equals($stored, self::of($event));
    }

    /**
     * Record that this event is what the relays now hold for this record.
     *
     * ONLY EVER CALLED AFTER A SUCCESSFUL TRANSMISSION, and of the event that was
     * actually signed and sent — not of one built earlier for the staleness scan. If a
     * send fails, the fingerprint stays where it was and the record is picked up again
     * on the next run, which is what makes a failed relay a delay rather than a silent
     * loss.
     *
     * WRITTEN AS A BARE UPDATE, past Eloquent, on purpose. Three things must not happen:
     *
     *  - `updated_at` must not move. It is an input other code reads as "when did this
     *    record last change", and a transmission is not a change to the record. It is
     *    also the field a naive version of this whole feature would have keyed on, so
     *    moving it here would corrupt the very signal that made it the wrong choice.
     *  - No model events. `Meetup` and `MeetupEvent` both carry
     *    {@see App\Observers\ApiChangeObserver}, so an ordinary `save()` would write an
     *    `api_changes` row, broadcast it and dispatch webhook deliveries — a public
     *    "this meetup changed" for every calendar refresh, with no field a consumer can
     *    see. `saveQuietly()` would fix that half and still move the timestamp.
     *  - No other column. A `save()` would persist whatever else happens to be dirty on
     *    the model; this statement carries exactly one column.
     *
     * The in-memory model is brought in line afterwards and marked clean, so a caller
     * that keeps using the object sees the stored value and does not carry a phantom
     * dirty attribute into its next `save()`.
     */
    public static function remember(Meetup|MeetupEvent $record, Event $event): string
    {
        $fingerprint = self::of($event);

        $record->getConnection()
            ->table($record->getTable())
            ->where($record->getKeyName(), $record->getKey())
            ->update([self::COLUMN => $fingerprint]);

        $record->setAttribute(self::COLUMN, $fingerprint);
        $record->syncOriginalAttribute(self::COLUMN);

        return $fingerprint;
    }
}
