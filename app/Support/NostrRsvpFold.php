<?php

namespace App\Support;

use App\Enums\NostrRsvpStatus;

/**
 * Validates kind 31925 RSVPs and kind 5 deletions read from relays, and folds them into
 * the one stored answer per (meetup event, pubkey).
 *
 * Pure: no database, no clock, no network. Everything the rules depend on is passed in,
 * so every rule below is a unit test away from being proven.
 *
 * ## The rules, in the order they are applied
 *
 * 1. Duplicates (the same event id from two relays) collapse to one; an RSVP that is
 *    already the stored winner is not looked at again.
 * 2. The NIP-01 id and signature must verify ({@see NostrEventVerifier}).
 * 3. `created_at` may be at most {@see self::MAX_CLOCK_SKEW_SECONDS} in the future.
 * 4. An RSVP carries exactly one `a` tag, and it is EXACTLY `31923:<pubkey>:<d>` for a
 *    configured publisher key — a coordinate that merely points at a portal-looking
 *    `d` tag under another key is somebody else's calendar event, not ours.
 * 5. That coordinate belongs to a published, not cancelled, current meetup event whose
 *    meetup accepts RSVPs AND shows its attendees publicly (D12a: a Nostr RSVP is public
 *    by nature, so it is not collected where the portal promises the opposite).
 * 6. `status` is one of `accepted`, `tentative`, `declined`.
 * 7. A NIP-09 deletion is honoured only from the RSVP's own key: by `e` = event id, or
 *    by `a` = `31925:<same pubkey>:<d>` for every version up to the deletion's time.
 * 8. Per (event, pubkey) the newest `created_at` wins; on a tie the lowest event id
 *    (the NIP-01 rule for replaceable events). A stored row takes part in the same
 *    comparison, so an older RSVP arriving late never overwrites a newer stored one.
 */
final class NostrRsvpFold
{
    public const KIND_RSVP = 31925;

    public const KIND_DELETION = 5;

    public const KIND_TIME_BASED_EVENT = 31923;

    public const MAX_CLOCK_SKEW_SECONDS = 900;

    public const MAX_D_TAG_LENGTH = 255;

    public const DROP_INVALID_SIGNATURE = 'invalid_signature';

    public const DROP_FUTURE_CREATED_AT = 'future_created_at';

    public const DROP_UNEXPECTED_KIND = 'unexpected_kind';

    public const DROP_MISSING_COORDINATE = 'missing_coordinate';

    public const DROP_AMBIGUOUS_COORDINATE = 'ambiguous_coordinate';

    public const DROP_FOREIGN_COORDINATE = 'foreign_coordinate';

    public const DROP_UNKNOWN_EVENT = 'unknown_event';

    public const DROP_EVENT_CANCELLED = 'event_cancelled';

    public const DROP_EVENT_OUT_OF_WINDOW = 'event_out_of_window';

    public const DROP_RSVP_DISABLED = 'rsvp_disabled';

    public const DROP_ATTENDEES_NOT_PUBLIC = 'attendees_not_public';

    public const DROP_INVALID_STATUS = 'invalid_status';

    public const DROP_INVALID_D_TAG = 'invalid_d_tag';

    public const DROP_DELETED = 'deleted';

    /**
     * The `a` values of the RSVPs that point at a configured publisher's kind 31923 —
     * the only coordinates worth resolving to a meetup event before folding.
     *
     * @param  list<array<string, mixed>>  $events
     * @param  list<string>  $publisherPubkeys
     * @return list<string>
     */
    public static function referencedCoordinates(array $events, array $publisherPubkeys): array
    {
        $coordinates = [];

        foreach ($events as $event) {
            if (($event['kind'] ?? null) !== self::KIND_RSVP || ! is_array($event['tags'] ?? null)) {
                continue;
            }

            foreach (self::tagValues($event['tags'], 'a') as $value) {
                if (self::isPublisherCoordinate($value, $publisherPubkeys)) {
                    $coordinates[$value] = true;
                }
            }
        }

        return array_keys($coordinates);
    }

    /**
     * @param  list<array<string, mixed>>  $events  Decoded events from all relays that answered completely.
     * @param  list<string>  $publisherPubkeys  Hex pubkeys whose kind 31923 the portal publishes.
     * @param  array<string, NostrRsvpTarget>  $targets  Published coordinate => its meetup event.
     * @param  array<string, array{nostr_event_id: string, d_tag: string, rsvp_created_at: int}>  $stored  "<meetupEventId>:<pubkey>" => current row.
     */
    public static function fold(array $events, array $publisherPubkeys, array $targets, array $stored, int $now): NostrRsvpFoldResult
    {
        $dropped = [];
        $drop = function (string $reason) use (&$dropped): void {
            $dropped[$reason] = ($dropped[$reason] ?? 0) + 1;
        };

        $rsvps = [];
        $deletedIds = [];
        $deletedAddresses = [];

        // The stored winners come back on every read. Re-verifying them would change
        // nothing and costs ~44 ms per event in pure PHP (measured 2026-09-17), so an
        // event whose id is already stored is skipped before the signature check. A
        // forgery carrying a stored id is skipped too — and is harmless, because nothing
        // of it is used.
        $storedIds = array_flip(array_column($stored, 'nostr_event_id'));

        foreach (self::uniqueById($events) as $event) {
            if (($event['kind'] ?? null) === self::KIND_RSVP && isset($storedIds[$event['id'] ?? null])) {
                continue;
            }

            if (! NostrEventVerifier::verify($event)) {
                $drop(self::DROP_INVALID_SIGNATURE);

                continue;
            }

            if ($event['created_at'] > $now + self::MAX_CLOCK_SKEW_SECONDS) {
                $drop(self::DROP_FUTURE_CREATED_AT);

                continue;
            }

            match ($event['kind']) {
                self::KIND_RSVP => $rsvps[] = $event,
                self::KIND_DELETION => self::collectDeletion($event, $deletedIds, $deletedAddresses),
                default => $drop(self::DROP_UNEXPECTED_KIND),
            };
        }

        $isDeleted = function (string $pubkey, string $id, string $dTag, int $createdAt) use (&$deletedIds, &$deletedAddresses): bool {
            return isset($deletedIds[$pubkey][$id])
                || (isset($deletedAddresses[$pubkey][$dTag]) && $createdAt <= $deletedAddresses[$pubkey][$dTag]);
        };

        /** @var array<string, array{meetup_event_id: int, pubkey: string, nostr_event_id: string, d_tag: string, status: string, rsvp_created_at: int}> $winners */
        $winners = [];

        foreach ($rsvps as $event) {
            $candidate = self::validateRsvp($event, $publisherPubkeys, $targets);

            if (is_string($candidate)) {
                $drop($candidate);

                continue;
            }

            if ($isDeleted($candidate['pubkey'], $candidate['nostr_event_id'], $candidate['d_tag'], $candidate['rsvp_created_at'])) {
                $drop(self::DROP_DELETED);

                continue;
            }

            $key = $candidate['meetup_event_id'].':'.$candidate['pubkey'];

            if (! isset($winners[$key]) || self::isNewer($candidate, $winners[$key])) {
                $winners[$key] = $candidate;
            }
        }

        $upserts = [];
        $deletions = [];

        foreach ($stored as $key => $row) {
            [$meetupEventId, $pubkey] = explode(':', $key, 2);
            $storedIsDeleted = $isDeleted($pubkey, $row['nostr_event_id'], $row['d_tag'], $row['rsvp_created_at']);

            if (isset($winners[$key])) {
                if (! $storedIsDeleted && ! self::isNewer($winners[$key], $row)) {
                    unset($winners[$key]);
                }

                continue;
            }

            if ($storedIsDeleted) {
                $deletions[] = ['meetup_event_id' => (int) $meetupEventId, 'pubkey' => $pubkey];
            }
        }

        ksort($winners);

        foreach ($winners as $winner) {
            $upserts[] = $winner;
        }

        ksort($dropped);

        return new NostrRsvpFoldResult($upserts, $deletions, $dropped);
    }

    /**
     * The candidate row for a verified kind 31925, or the reason it is dropped.
     *
     * @param  array<string, mixed>  $event
     * @param  list<string>  $publisherPubkeys
     * @param  array<string, NostrRsvpTarget>  $targets
     * @return array{meetup_event_id: int, pubkey: string, nostr_event_id: string, d_tag: string, status: string, rsvp_created_at: int}|string
     */
    private static function validateRsvp(array $event, array $publisherPubkeys, array $targets): array|string
    {
        $coordinates = self::tagValues($event['tags'], 'a');

        if ($coordinates === []) {
            return self::DROP_MISSING_COORDINATE;
        }

        if (count($coordinates) > 1) {
            return self::DROP_AMBIGUOUS_COORDINATE;
        }

        $coordinate = $coordinates[0];

        if (! self::isPublisherCoordinate($coordinate, $publisherPubkeys)) {
            return self::DROP_FOREIGN_COORDINATE;
        }

        $target = $targets[$coordinate] ?? null;

        $reason = match (true) {
            $target === null => self::DROP_UNKNOWN_EVENT,
            $target->cancelled => self::DROP_EVENT_CANCELLED,
            ! $target->inWindow => self::DROP_EVENT_OUT_OF_WINDOW,
            ! $target->rsvpEnabled => self::DROP_RSVP_DISABLED,
            ! $target->attendeesPublic => self::DROP_ATTENDEES_NOT_PUBLIC,
            default => null,
        };

        if ($reason !== null) {
            return $reason;
        }

        $statuses = array_values(array_unique(self::tagValues($event['tags'], 'status')));
        $status = count($statuses) === 1 ? NostrRsvpStatus::tryFrom($statuses[0]) : null;

        if ($status === null) {
            return self::DROP_INVALID_STATUS;
        }

        // NIP-01: an addressable event without a `d` tag has the empty string as its `d`.
        $dTag = self::tagValues($event['tags'], 'd')[0] ?? '';

        if (mb_strlen($dTag) > self::MAX_D_TAG_LENGTH) {
            return self::DROP_INVALID_D_TAG;
        }

        return [
            'meetup_event_id' => $target->meetupEventId,
            'pubkey' => $event['pubkey'],
            'nostr_event_id' => $event['id'],
            'd_tag' => $dTag,
            'status' => $status->value,
            'rsvp_created_at' => $event['created_at'],
        ];
    }

    /**
     * Records what a verified kind 5 withdraws — only ever on behalf of its own key.
     *
     * @param  array<string, mixed>  $event
     * @param  array<string, array<string, true>>  $deletedIds
     * @param  array<string, array<string, int>>  $deletedAddresses
     */
    private static function collectDeletion(array $event, array &$deletedIds, array &$deletedAddresses): void
    {
        $pubkey = $event['pubkey'];

        foreach (self::tagValues($event['tags'], 'e') as $id) {
            $deletedIds[$pubkey][$id] = true;
        }

        foreach (self::tagValues($event['tags'], 'a') as $address) {
            $parts = explode(':', $address, 3);

            if (count($parts) !== 3 || $parts[0] !== (string) self::KIND_RSVP || $parts[1] !== $pubkey) {
                continue;
            }

            $deletedAddresses[$pubkey][$parts[2]] = max($deletedAddresses[$pubkey][$parts[2]] ?? 0, $event['created_at']);
        }
    }

    /**
     * @param  list<string>  $publisherPubkeys
     */
    private static function isPublisherCoordinate(string $coordinate, array $publisherPubkeys): bool
    {
        $parts = explode(':', $coordinate, 3);

        return count($parts) === 3
            && $parts[0] === (string) self::KIND_TIME_BASED_EVENT
            && in_array($parts[1], $publisherPubkeys, true)
            && $parts[2] !== '';
    }

    /**
     * @param  array{rsvp_created_at: int, nostr_event_id: string}  $candidate
     * @param  array{rsvp_created_at: int, nostr_event_id: string}  $current
     */
    private static function isNewer(array $candidate, array $current): bool
    {
        if ($candidate['rsvp_created_at'] !== $current['rsvp_created_at']) {
            return $candidate['rsvp_created_at'] > $current['rsvp_created_at'];
        }

        return strcmp($candidate['nostr_event_id'], $current['nostr_event_id']) < 0;
    }

    /**
     * @param  array<mixed>  $tags
     * @return list<string>
     */
    private static function tagValues(array $tags, string $name): array
    {
        $values = [];

        foreach ($tags as $tag) {
            if (is_array($tag) && ($tag[0] ?? null) === $name && is_string($tag[1] ?? null)) {
                $values[] = $tag[1];
            }
        }

        return $values;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private static function uniqueById(array $events): array
    {
        $unique = [];

        foreach ($events as $event) {
            $id = $event['id'] ?? null;

            if (! is_string($id)) {
                $unique[] = $event;

                continue;
            }

            $unique[$id] ??= $event;
        }

        return array_values($unique);
    }
}
