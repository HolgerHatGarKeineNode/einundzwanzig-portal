<?php

namespace App\Support;

use App\Models\Meetup;
use App\Models\MeetupEvent;
use swentel\nostr\Event\Event;

/**
 * How often the payload a record would publish has been rejected in a row, and the
 * decision to stop offering it.
 *
 * ## The problem this removes
 *
 * `nostr:publish-calendar` ends its run at the first rejected transmission and leaves
 * the records behind it untouched (see the command for why: a relay set that rejects
 * one event is not asked to take the next 24 in the same minute). For a relay having a
 * bad minute that is a delay. For a payload no relay will ever accept it is a block:
 * the record keeps its place at the head of the queue and every five-minute run spends
 * itself on it again. The `MeetupEvent` queue is ordered by `start` and gated on
 * `start > now()`, so the records behind it do not just wait — every one whose start
 * passes in the meantime leaves the queue unpublished.
 *
 * After {@see self::MAX_ATTEMPTS} consecutive rejections of the SAME payload the
 * command skips the record and carries on with the rest of the batch.
 *
 * ## Why the payload, and not the record, is what gets given up on
 *
 * `nostr_publish_failed_hash` holds the {@see NostrPayloadFingerprint} of the payload
 * that was rejected. A record is skipped only while the payload built today still
 * hashes to that value — so an organiser who edits the description puts the record
 * straight back into the queue, from zero, with nobody running anything. A bare counter
 * would drop the record for good, which is the same loss this class exists to prevent,
 * only quieter and permanent.
 *
 * The fingerprint is the right key for this and not merely a convenient one: it covers
 * kind, tags and content and excludes `created_at`, i.e. exactly what the relay judges
 * and nothing that moves between two builds of an unchanged record. Any hash that
 * included the clock would reset the counter on every run and the skip would never
 * trigger.
 *
 * ## What is NOT recorded here
 *
 * Nothing about WHY the transmission failed. {@see NostrEventTransmitter} reports one
 * boolean for the whole relay set, so "the relays were down" and "this event is
 * malformed" arrive as the same answer. The counter therefore does not diagnose; it
 * bounds the damage of either. Three runs of a scheduled command are 15 minutes — long
 * enough that a passing outage does not consume a record's attempts, short enough that
 * a poisoned record stops holding up a queue within the same quarter of an hour.
 *
 * ## An outage is not a bad payload, and this class cannot tell them apart alone
 *
 * WITHOUT A SECOND MECHANISM, AN OUTAGE LONGER THAN THREE RUNS GIVES UP ON RECORDS THAT
 * ARE FINE. With every relay unreachable the run fails at the head of the queue, so that
 * head collects three rejections in 15 minutes and is stepped over; the record behind it
 * becomes the new head and collects its own three. Roughly one record per three runs is
 * marked that way, and since its payload never changed, nothing in the counting above
 * lifts the mark once the relays are back.
 *
 * THAT SECOND MECHANISM IS {@see \App\Console\Commands\Nostr\PublishCalendarEvents::reoffer()}.
 * A record given up on is offered once more at the end of any run in which ANOTHER
 * record was accepted — that acceptance is the proof the relay set is up which a single
 * boolean per send cannot give, and it is the only such proof available here. The count
 * is cleared by {@see self::clear()} when the re-offer succeeds, and kept, with a
 * warning saying exactly that, when it fails while another record succeeded: then it
 * really is the payload.
 *
 * What remains is narrow and self-correcting: during the outage itself nothing is
 * accepted, so nothing is re-offered, and the marks wait for the first run that
 * publishes anything at all.
 */
final class NostrPublishFailures
{
    /**
     * Consecutive rejections of one payload after which it is no longer offered.
     *
     * Three, not one: a single failure is far more often the relays than the payload,
     * and a record must not fall out of the run for the cost of one bad connection.
     */
    public const MAX_ATTEMPTS = 3;

    /**
     * The columns both models carry this in. Named once so a rename cannot half-happen.
     */
    public const ATTEMPTS_COLUMN = 'nostr_publish_attempts';

    public const HASH_COLUMN = 'nostr_publish_failed_hash';

    /**
     * Whether this exact payload has been rejected often enough to stop offering it.
     *
     * Both halves are required, and the hash is the half that matters: a record with
     * three failures behind it but a DIFFERENT payload today is not skipped, because
     * what failed is not what would be sent.
     */
    public static function hasGivenUpOn(Meetup|MeetupEvent $record, Event $event): bool
    {
        if ((int) $record->getAttribute(self::ATTEMPTS_COLUMN) < self::MAX_ATTEMPTS) {
            return false;
        }

        $stored = $record->getAttribute(self::HASH_COLUMN);

        return is_string($stored) && hash_equals($stored, NostrPayloadFingerprint::of($event));
    }

    /**
     * Count one rejected transmission of this payload and return the new count.
     *
     * CONSECUTIVE, and the hash is what makes the word true: a failure of a payload
     * other than the stored one starts the count again at 1 rather than adding to a
     * tally about a text that no longer exists.
     *
     * WRITTEN AS A BARE UPDATE, past Eloquent, for the reasons spelled out in
     * {@see NostrPayloadFingerprint::remember()} — `updated_at` must not move for a
     * transmission that did not change the record, and
     * {@see \App\Observers\ApiChangeObserver} must not announce a public "this meetup
     * changed" for a failed send. Here there is a third reason: the model in the
     * command's hand may carry a `nostr_coordinate` that was deliberately NOT saved,
     * and a `save()` would persist it.
     */
    public static function recordFailure(Meetup|MeetupEvent $record, Event $event): int
    {
        $fingerprint = NostrPayloadFingerprint::of($event);
        $stored = $record->getAttribute(self::HASH_COLUMN);

        $attempts = is_string($stored) && hash_equals($stored, $fingerprint)
            ? (int) $record->getAttribute(self::ATTEMPTS_COLUMN) + 1
            : 1;

        self::write($record, $attempts, $fingerprint);

        return $attempts;
    }

    /**
     * Forget a record's failure history — called after a transmission it accepted.
     *
     * A no-op when there is nothing to forget, so the ordinary successful publish costs
     * no second statement.
     */
    public static function clear(Meetup|MeetupEvent $record): void
    {
        if ((int) $record->getAttribute(self::ATTEMPTS_COLUMN) === 0
            && $record->getAttribute(self::HASH_COLUMN) === null) {
            return;
        }

        self::write($record, 0, null);
    }

    /**
     * Persist both columns and bring the in-memory model in line, without model events
     * and without moving any other column.
     */
    private static function write(Meetup|MeetupEvent $record, int $attempts, ?string $fingerprint): void
    {
        $record->getConnection()
            ->table($record->getTable())
            ->where($record->getKeyName(), $record->getKey())
            ->update([
                self::ATTEMPTS_COLUMN => $attempts,
                self::HASH_COLUMN => $fingerprint,
            ]);

        $record->setAttribute(self::ATTEMPTS_COLUMN, $attempts);
        $record->setAttribute(self::HASH_COLUMN, $fingerprint);
        $record->syncOriginalAttribute(self::ATTEMPTS_COLUMN);
        $record->syncOriginalAttribute(self::HASH_COLUMN);
    }
}
