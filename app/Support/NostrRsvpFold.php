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
 * THE ORDER IS A SECURITY PROPERTY, not a style. A BIP-340 check costs ~47 ms in pure
 * PHP and a stranger can put any number of events in front of it, so everything that can
 * be decided from the tags alone is decided FIRST, and only what survives is verified —
 * bounded by {@see self::DEFAULT_MAX_VERIFICATIONS} per run. An event that fails a cheap
 * rule never costs a signature check.
 *
 * 1. An RSVP that is already the stored winner is not looked at again; events that share
 *    an id are tried in turn and the one that VERIFIES wins (never the first arrival).
 * 2. `created_at` may be at most {@see self::MAX_CLOCK_SKEW_SECONDS} in the future.
 * 3. An RSVP carries exactly one `a` tag, and it is EXACTLY `31923:<pubkey>:<d>` for a
 *    configured publisher key — a coordinate that merely points at a portal-looking
 *    `d` tag under another key is somebody else's calendar event, not ours.
 * 4. That coordinate belongs to a published, not cancelled, current meetup event whose
 *    meetup accepts RSVPs AND shows its attendees publicly (D12a: a Nostr RSVP is public
 *    by nature, so it is not collected where the portal promises the opposite).
 * 5. `status` is one of `accepted`, `tentative`, `declined`.
 * 6. A deletion is only looked at when its author is a key this portal stores an RSVP of,
 *    or one that just sent a candidate — nobody else's kind 5 can cost anything.
 * 7. ONLY NOW the NIP-01 id and signature are checked ({@see NostrEventVerifier}).
 * 8. A NIP-09 deletion is honoured only from the RSVP's own key: by `e` = event id, or
 *    by `a` = `31925:<same pubkey>:<d>` for every version up to the deletion's time.
 * 9. Per (event, pubkey) the newest `created_at` wins; on a tie the lowest event id
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

    /**
     * Signature checks one run may spend. At the measured 47 ms each, this is ~47 s of
     * CPU — a quarter of the five-minute cadence and far inside the ten-minute overlap
     * lock the scheduler holds. A run that hits the cap is INCOMPLETE, and the command
     * treats it like a relay that never answered: nothing is counted as absent and no
     * cursor moves.
     */
    public const DEFAULT_MAX_VERIFICATIONS = 1000;

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

    public const DROP_IRRELEVANT_DELETION = 'irrelevant_deletion';

    public const DROP_MALFORMED = 'malformed';

    public const DROP_VERIFICATION_BUDGET = 'verification_budget';

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
     * @param  (callable(array<string, mixed>): bool)|null  $verify  The signature check; injectable so a test can count its calls.
     */
    public static function fold(
        array $events,
        array $publisherPubkeys,
        array $targets,
        array $stored,
        int $now,
        ?callable $verify = null,
        int $maxVerifications = self::DEFAULT_MAX_VERIFICATIONS,
    ): NostrRsvpFoldResult {
        $verify ??= NostrEventVerifier::verify(...);

        $dropped = [];
        $drop = function (string $reason, int $times = 1) use (&$dropped): void {
            $dropped[$reason] = ($dropped[$reason] ?? 0) + $times;
        };

        // The stored winners come back on every read. Re-checking them would change
        // nothing and costs a signature verification each, so an event whose id is
        // already stored is skipped. A forgery carrying a stored id is skipped too — and
        // is harmless, because nothing of it is used.
        $storedIds = array_flip(array_column($stored, 'nostr_event_id'));
        $storedPubkeys = [];

        foreach (array_keys($stored) as $key) {
            $storedPubkeys[explode(':', $key, 2)[1]] = true;
        }

        /** @var array<string, list<array{event: array<string, mixed>, candidate: array<string, mixed>}>> $rsvpsById */
        $rsvpsById = [];
        $candidatePubkeys = [];
        $deletionEvents = [];

        foreach (self::byId($events) as $id => $variants) {
            if (preg_match('/^[0-9a-f]{64}$/', (string) $id) !== 1) {
                // Without a well-formed id an event cannot be addressed, stored or
                // deduplicated — the cheapest possible rejection.
                $drop(self::DROP_MALFORMED, count($variants));

                continue;
            }

            foreach ($variants as $event) {
                $kind = $event['kind'] ?? null;

                if ($kind === self::KIND_RSVP && isset($storedIds[$id])) {
                    continue;
                }

                if (! is_int($event['created_at'] ?? null) || $event['created_at'] > $now + self::MAX_CLOCK_SKEW_SECONDS) {
                    $drop(self::DROP_FUTURE_CREATED_AT);

                    continue;
                }

                if ($kind === self::KIND_DELETION) {
                    $deletionEvents[] = $event;

                    continue;
                }

                if ($kind !== self::KIND_RSVP) {
                    $drop(self::DROP_UNEXPECTED_KIND);

                    continue;
                }

                $candidate = self::validateRsvp($event, $publisherPubkeys, $targets);

                if (is_string($candidate)) {
                    $drop($candidate);

                    continue;
                }

                $rsvpsById[$id][] = ['event' => $event, 'candidate' => $candidate];
                $candidatePubkeys[$candidate['pubkey']] = true;
            }
        }

        // A kind 5 from a key this portal holds no RSVP of, and that sent none in this
        // batch, cannot remove anything — it is dropped before it can cost a signature
        // check.
        $relevantDeletions = [];

        foreach ($deletionEvents as $event) {
            $pubkey = $event['pubkey'] ?? null;

            if (is_string($pubkey) && (isset($storedPubkeys[$pubkey]) || isset($candidatePubkeys[$pubkey]))) {
                $relevantDeletions[] = $event;

                continue;
            }

            $drop(self::DROP_IRRELEVANT_DELETION);
        }

        $budget = $maxVerifications;
        $exhausted = false;

        $spend = function (array $event) use ($verify, &$budget, &$exhausted, $drop): ?bool {
            if ($budget <= 0) {
                $exhausted = true;
                $drop(self::DROP_VERIFICATION_BUDGET);

                return null;
            }

            $budget--;

            return (bool) $verify($event);
        };

        $deletedIds = [];
        $deletedAddresses = [];

        // Deletions first: they take counts AWAY, so of the two kinds they are the one a
        // squeezed run must not skip.
        foreach ($relevantDeletions as $event) {
            $verified = $spend($event);

            if ($verified === null) {
                break;
            }

            if (! $verified) {
                $drop(self::DROP_INVALID_SIGNATURE);

                continue;
            }

            self::collectDeletion($event, $deletedIds, $deletedAddresses);
        }

        $isDeleted = function (string $pubkey, string $id, string $dTag, int $createdAt) use (&$deletedIds, &$deletedAddresses): bool {
            return isset($deletedIds[$pubkey][$id])
                || (isset($deletedAddresses[$pubkey][$dTag]) && $createdAt <= $deletedAddresses[$pubkey][$dTag]);
        };

        /** @var array<string, array{meetup_event_id: int, pubkey: string, nostr_event_id: string, d_tag: string, status: string, rsvp_created_at: int}> $winners */
        $winners = [];

        foreach ($rsvpsById as $variants) {
            foreach ($variants as $variant) {
                $verified = $spend($variant['event']);

                if ($verified === null) {
                    break 2;
                }

                if (! $verified) {
                    $drop(self::DROP_INVALID_SIGNATURE);

                    continue;
                }

                $candidate = $variant['candidate'];

                if ($isDeleted($candidate['pubkey'], $candidate['nostr_event_id'], $candidate['d_tag'], $candidate['rsvp_created_at'])) {
                    $drop(self::DROP_DELETED);

                    break;
                }

                $key = $candidate['meetup_event_id'].':'.$candidate['pubkey'];

                if (! isset($winners[$key]) || self::isNewer($candidate, $winners[$key])) {
                    $winners[$key] = $candidate;
                }

                // An id binds its content, so at most one variant of it can verify.
                break;
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

        return new NostrRsvpFoldResult($upserts, $deletions, $dropped, $maxVerifications - $budget, $exhausted);
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
     * The events grouped by id, in first-seen order, WITHOUT deciding yet which variant
     * of a shared id counts — that is what verification decides (finding F6).
     *
     * @param  list<array<string, mixed>>  $events
     * @return array<string, list<array<string, mixed>>>
     */
    private static function byId(array $events): array
    {
        $byId = [];

        foreach ($events as $event) {
            $id = $event['id'] ?? null;

            $byId[is_string($id) ? $id : ''][] = $event;
        }

        return $byId;
    }
}
