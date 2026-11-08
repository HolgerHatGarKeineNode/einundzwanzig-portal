<?php

namespace App\Console\Commands\Nostr;

use App\Models\MeetupEvent;
use App\Models\MeetupEventNostrRsvp;
use App\Models\User;
use App\Support\NostrCalendarEventFactory;
use App\Support\NostrRelayReader;
use App\Support\NostrRsvpFold;
use App\Support\NostrRsvpFoldResult;
use App\Support\NostrRsvpTarget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use swentel\nostr\Key\Key;
use Throwable;

/**
 * Hybrid RSVP (D12): reads the kind 31925 answers to the portal's own kind 31923 events
 * back from the configured relays and stores the newest valid one per key and event.
 *
 * ## What one run does
 *
 * 1. Scope: every meetup event that is published (`nostr_coordinate`), not cancelled,
 *    not more than {@see self::PAST_GRACE_HOURS} hours past its start, and whose meetup
 *    has `rsvp_enabled` and `attendees_public`. Nothing in scope → no socket.
 * 2. Per relay, ONE read ({@see NostrRelayReader}): kind 31925 by `#a` in chunks of
 *    {@see self::COORDINATES_PER_FILTER}, a secondary `#p` = publisher filter for
 *    clients that tag the author, and kind 5 by the keys already stored — a NIP-09
 *    deletion carries neither the calendar coordinate nor the publisher key, so it can
 *    only be found by its author. Only when the read surfaces keys never stored before
 *    does a second read ask for THEIR deletions.
 * 3. A relay that did not confirm EOSE for every read is UNKNOWN: none of its events are
 *    used and its cursor stays where it was. An unknown relay is never read as "no
 *    RSVPs" — that is the one mistake that would make counts silently drop.
 * 4. The events of the relays that answered completely are folded
 *    ({@see NostrRsvpFold}) against the stored rows and written in one transaction.
 * 5. Every stored row is re-linked to the account whose `users.nostr` is its npub.
 * 6. Each complete relay's cursor moves to the START of this run.
 *
 * ## Cursor and full pass
 *
 * Incremental reads ask for `since = last complete run − 1 h`: the hour absorbs relay
 * propagation and ordinary clock drift. `created_at` is the author's claim, so an RSVP
 * back-dated further than that is only seen by the FULL pass (no `since`), which runs
 * per relay when its last complete full pass is {@see self::FULL_PASS_INTERVAL_SECONDS}
 * old — and on the first run, and with `--full`. Cursors live in the cache: losing them
 * costs one full pass, never an answer.
 *
 * ## Never the attendee JSON
 *
 * This command writes `meetup_event_nostr_rsvps` and nothing else. The attendee lists on
 * `meetup_events` are maintained by an unlocked read-modify-write in the web and API
 * paths; see the table migration.
 */
#[Signature('nostr:ingest-rsvps {--full : Ignore the cursors and read every RSVP of the events in scope}')]
#[Description('Read NIP-52 RSVPs (kind 31925) to the portal\'s published meetup events from the configured relays and store them for counting')]
class IngestNostrRsvps extends Command
{
    public const COORDINATES_PER_FILTER = 20;

    public const AUTHORS_PER_FILTER = 100;

    public const FILTER_LIMIT = 500;

    /**
     * Keys whose deletions are asked for in one run. The stored keys are swept in blocks
     * of this size, block by block across runs (`deletion_offset` in the cursor), so a
     * table an attacker has filled costs a fixed number of filters per run instead of a
     * growing one. A withdrawal is therefore honoured within one sweep of the table —
     * at five-minute runs that is 500 keys / 5 min, plus the immediate second read for
     * keys seen for the first time.
     */
    public const DELETION_AUTHORS_PER_RUN = 500;

    /**
     * Unlinked RSVPs stored per meetup event. "+N via Nostr" says how many strangers
     * answered; beyond this it says "a lot", and the portal stops paying for more rows.
     * Linked answers (a key that belongs to an account) are never refused.
     */
    public const MAX_UNLINKED_RSVPS_PER_EVENT = 200;

    /**
     * Wall-clock seconds one run may take. The scheduler holds a ten-minute overlap lock
     * and ticks every five; a run that would outlive its own lock is the one case in
     * which two runs could fold against the same snapshot and put an older answer back
     * over a newer one. Hitting the budget makes the run INCOMPLETE: no cursor moves.
     */
    public const MAX_RUN_SECONDS = 240;

    public const CURSOR_OVERLAP_SECONDS = 3600;

    public const FULL_PASS_INTERVAL_SECONDS = 3600;

    /**
     * An event stays in scope for a day after its start: late answers and relay lag are
     * still counted, and after that the counts of a past event are frozen.
     */
    public const PAST_GRACE_HOURS = 24;

    private const CURSOR_CACHE_PREFIX = 'nostr:ingest-rsvps:cursor:';

    public function handle(NostrRelayReader $reader): int
    {
        $publisherPubkeys = $this->publisherPubkeys();

        if ($publisherPubkeys === null) {
            $this->error('NOSTR_PUBLISHER_NSEC ist nicht gesetzt oder ungültig.');

            return self::FAILURE;
        }

        $relays = array_values(array_filter((array) config('services.nostr.relays', []), 'is_string'));

        if ($relays === []) {
            $this->error('No relays configured (NOSTR_RELAYS).');

            return self::FAILURE;
        }

        $runStartedAt = now()->getTimestamp();
        $deadline = $runStartedAt + self::MAX_RUN_SECONDS;
        $scope = $this->eventsInScope($publisherPubkeys);

        if ($scope->isEmpty()) {
            $this->info('No published upcoming meetup event accepts Nostr RSVPs.');

            return self::SUCCESS;
        }

        $events = [];
        $complete = [];
        $unknown = [];
        $outOfTime = false;

        foreach ($relays as $relayUrl) {
            $cursor = Cache::get($this->cursorKey($relayUrl), []);

            if (now()->getTimestamp() >= $deadline) {
                $outOfTime = true;
                $unknown[$relayUrl] = 'run budget of '.self::MAX_RUN_SECONDS.' s spent before this relay was read';

                continue;
            }

            $fullPass = $this->option('full')
                || ! isset($cursor['last_ok'])
                || $runStartedAt - (int) ($cursor['last_full'] ?? 0) >= self::FULL_PASS_INTERVAL_SECONDS;
            $since = $fullPass ? null : max(1, (int) $cursor['last_ok'] - self::CURSOR_OVERLAP_SECONDS);
            [$storedPubkeys, $nextDeletionOffset] = $this->deletionAuthorBlock($scope->keys()->all(), (int) ($cursor['deletion_offset'] ?? 0));

            $read = $reader->read($relayUrl, [
                ...$this->rsvpFilters($scope->values()->all(), $since),
                ...$this->deletionFilters($storedPubkeys, $since),
            ]);

            if (! $read->complete) {
                $unknown[$relayUrl] = $read->failure;

                continue;
            }

            $this->warnIfTruncated($relayUrl, $read->events, $scope->values()->all());

            // A key seen for the first time may already have withdrawn its RSVP, and that
            // deletion was not asked for above. Only then does the relay get a second read.
            $newAuthors = collect($read->events)
                ->where('kind', NostrRsvpFold::KIND_RSVP)
                ->pluck('pubkey')
                ->filter(fn ($pubkey): bool => is_string($pubkey) && preg_match('/^[0-9a-f]{64}$/', $pubkey) === 1)
                ->diff($storedPubkeys)
                ->unique()
                ->values()
                ->all();

            if ($newAuthors !== []) {
                $deletions = $reader->read($relayUrl, $this->deletionFilters($newAuthors, $since));

                if (! $deletions->complete) {
                    $unknown[$relayUrl] = $deletions->failure;

                    continue;
                }

                array_push($events, ...$deletions->events);
            }

            array_push($events, ...$read->events);
            $complete[$relayUrl] = ['full_pass' => $fullPass, 'deletion_offset' => $nextDeletionOffset];
        }

        foreach ($unknown as $relayUrl => $failure) {
            Log::warning('nostr:ingest-rsvps: relay answer unknown, its events and cursor are left untouched', [
                'relay' => $relayUrl,
                'failure' => $failure,
            ]);
            $this->warn("{$relayUrl}: unknown ({$failure}) — not counted, cursor not advanced");
        }

        if ($complete === []) {
            $this->error('No relay answered completely; nothing was changed.');

            return self::FAILURE;
        }

        $targets = $this->targetsFor(NostrRsvpFold::referencedCoordinates($events, $publisherPubkeys), $publisherPubkeys);
        $stored = $this->storedRows(collect($targets)->map->meetupEventId->merge($scope->keys())->unique()->values()->all());

        $result = NostrRsvpFold::fold($events, $publisherPubkeys, $targets, $stored, $runStartedAt);
        $refused = $this->apply($result);
        $relinked = $this->relinkUsers();

        // A run that ran out of signature budget or wall clock has NOT seen everything the
        // relays offered. Moving a cursor now would skip those events for good, so the
        // same rule as for a relay without EOSE applies: nothing moves.
        $incomplete = $result->verificationBudgetExhausted || $outOfTime || now()->getTimestamp() >= $deadline;

        if (! $incomplete) {
            foreach ($complete as $relayUrl => $state) {
                $cursor = Cache::get($this->cursorKey($relayUrl), []);

                Cache::forever($this->cursorKey($relayUrl), [
                    'last_ok' => $runStartedAt,
                    'last_full' => $state['full_pass'] ? $runStartedAt : ($cursor['last_full'] ?? null),
                    'deletion_offset' => $state['deletion_offset'],
                ]);
            }
        }

        $summary = [
            'relays_complete' => array_keys($complete),
            'relays_unknown' => array_keys($unknown),
            'events_read' => count($events),
            'verifications' => $result->verifications,
            'incomplete' => $incomplete,
            'upserts' => count($result->upserts),
            'deletions' => count($result->deletions),
            'refused_over_cap' => $refused,
            'relinked' => $relinked,
            'dropped' => $result->dropped,
        ];

        Log::info('nostr:ingest-rsvps finished', $summary);

        $this->info(sprintf(
            'Relays complete: %d, unknown: %d. Events read: %d. Stored: %d, removed: %d, relinked: %d.',
            count($complete),
            count($unknown),
            count($events),
            count($result->upserts),
            count($result->deletions),
            $relinked,
        ));

        foreach ($result->dropped as $reason => $count) {
            $this->line("Dropped {$count} × {$reason}");
        }

        if ($incomplete) {
            $this->error('Run incomplete (budget spent) — no cursor was advanced.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Writes what the fold decided, and refuses to store more UNLINKED answers for an
     * event than {@see self::MAX_UNLINKED_RSVPS_PER_EVENT} (finding F4).
     *
     * The linkage is resolved HERE rather than left to the re-link step, because it is
     * what decides whether a row falls under the cap: an answer that belongs to an
     * account is never refused, a stranger's is once the event has enough of them.
     *
     * @return int how many rows were refused
     */
    private function apply(NostrRsvpFoldResult $result): int
    {
        $userIdByPubkey = $this->userIdsFor(array_column($result->upserts, 'pubkey'));
        $unlinkedPerEvent = MeetupEventNostrRsvp::query()
            ->whereIn('meetup_event_id', array_unique(array_column($result->upserts, 'meetup_event_id')))
            ->whereNull('user_id')
            ->selectRaw('meetup_event_id, count(*) as aggregate')
            ->groupBy('meetup_event_id')
            ->pluck('aggregate', 'meetup_event_id')
            ->all();

        $refused = [];

        return DB::transaction(function () use ($result, $userIdByPubkey, $unlinkedPerEvent, &$refused): int {
            foreach ($result->upserts as $row) {
                $userId = $userIdByPubkey[$row['pubkey']] ?? null;
                $existing = MeetupEventNostrRsvp::query()
                    ->where('meetup_event_id', $row['meetup_event_id'])
                    ->where('pubkey', $row['pubkey'])
                    ->first();

                if ($existing === null && $userId === null) {
                    $stored = $unlinkedPerEvent[$row['meetup_event_id']] ?? 0;

                    if ($stored >= self::MAX_UNLINKED_RSVPS_PER_EVENT) {
                        $refused[$row['meetup_event_id']] = ($refused[$row['meetup_event_id']] ?? 0) + 1;

                        continue;
                    }

                    $unlinkedPerEvent[$row['meetup_event_id']] = $stored + 1;
                }

                MeetupEventNostrRsvp::query()->updateOrCreate(
                    ['meetup_event_id' => $row['meetup_event_id'], 'pubkey' => $row['pubkey']],
                    $existing === null ? [...$row, 'user_id' => $userId] : $row,
                );
            }

            foreach ($result->deletions as $row) {
                MeetupEventNostrRsvp::query()
                    ->where('meetup_event_id', $row['meetup_event_id'])
                    ->where('pubkey', $row['pubkey'])
                    ->delete();
            }

            if ($refused !== []) {
                // Once per run, not once per event: a flood is one fact, not N facts.
                Log::warning('nostr:ingest-rsvps: refused unlinked RSVPs over the per-event cap', [
                    'cap' => self::MAX_UNLINKED_RSVPS_PER_EVENT,
                    'per_meetup_event' => $refused,
                ]);
            }

            return array_sum($refused);
        });
    }

    /**
     * The block of stored keys whose deletions this run asks for, and the offset the next
     * run starts at. Bounded per run, so a table full of strangers' answers cannot turn
     * into an unbounded filter list (finding F2).
     *
     * @param  list<int>  $meetupEventIds
     * @return array{0: list<string>, 1: int}
     */
    private function deletionAuthorBlock(array $meetupEventIds, int $offset): array
    {
        $total = MeetupEventNostrRsvp::query()->whereIn('meetup_event_id', $meetupEventIds)->distinct()->count('pubkey');

        if ($total === 0) {
            return [[], 0];
        }

        $offset = $offset % max(1, $total);

        $pubkeys = MeetupEventNostrRsvp::query()
            ->whereIn('meetup_event_id', $meetupEventIds)
            ->distinct()
            ->orderBy('pubkey')
            ->offset($offset)
            ->limit(self::DELETION_AUTHORS_PER_RUN)
            ->pluck('pubkey')
            ->all();

        return [$pubkeys, ($offset + self::DELETION_AUTHORS_PER_RUN) % $total];
    }

    /**
     * The hex pubkey(s) whose kind 31923 count as the portal's own — derived from the
     * publishing key, never printed. Null when the key is missing or unusable.
     *
     * @return list<string>|null
     */
    private function publisherPubkeys(): ?array
    {
        $privateKey = config('services.nostr.publisher_key');

        if (! is_string($privateKey) || $privateKey === '') {
            return null;
        }

        try {
            $key = new Key;
            $hexKey = str_starts_with($privateKey, 'nsec') ? $key->convertToHex($privateKey) : $privateKey;

            return [$key->getPublicKey($hexKey)];
        } catch (Throwable) {
            // No message: the library's error could one day embed the key (see NostrWhoami).
            return null;
        }
    }

    /**
     * Event id => its published coordinate, for the events whose RSVPs are collected.
     *
     * A stored `nostr_coordinate` is used only when it is exactly the coordinate the
     * configured key would publish under, so a row carrying some other key's address
     * cannot pull that key's RSVPs in.
     *
     * @param  list<string>  $publisherPubkeys
     * @return Collection<int, string>
     */
    private function eventsInScope(array $publisherPubkeys): Collection
    {
        return MeetupEvent::query()
            ->whereNotNull('nostr_coordinate')
            ->whereNull('cancelled_at')
            ->where('start', '>=', now()->subHours(self::PAST_GRACE_HOURS))
            ->whereHas('meetup', fn ($meetup) => $meetup->where('rsvp_enabled', true)->where('attendees_public', true))
            ->get(['id', 'nostr_coordinate'])
            ->filter(fn (MeetupEvent $event): bool => in_array($event->nostr_coordinate, $this->expectedCoordinates($event, $publisherPubkeys), true))
            ->mapWithKeys(fn (MeetupEvent $event): array => [$event->id => $event->nostr_coordinate]);
    }

    /**
     * @param  list<string>  $publisherPubkeys
     * @return list<string>
     */
    private function expectedCoordinates(MeetupEvent $event, array $publisherPubkeys): array
    {
        return array_map(
            fn (string $pubkey): string => NostrCalendarEventFactory::coordinate(
                NostrRsvpFold::KIND_TIME_BASED_EVENT,
                $pubkey,
                NostrCalendarEventFactory::eventDTag($event),
            ),
            $publisherPubkeys,
        );
    }

    /**
     * The `#a` filters, and deliberately NOTHING else (finding F3).
     *
     * A secondary `#p = publisher` filter used to ride along for clients that tag the
     * calendar author. It cannot contribute a single countable RSVP: a countable one
     * carries exactly one `a` tag pointing at a published coordinate, and every such
     * coordinate is already in the `#a` filters above. What it did contribute was 500
     * stranger-chosen events per relay and run, each of which had to be looked at.
     *
     * @param  list<string>  $coordinates
     * @return list<array<string, mixed>>
     */
    private function rsvpFilters(array $coordinates, ?int $since): array
    {
        return array_map(
            fn (array $chunk): array => $this->bounded(['kinds' => [NostrRsvpFold::KIND_RSVP], '#a' => $chunk], $since),
            array_chunk($coordinates, self::COORDINATES_PER_FILTER),
        );
    }

    /**
     * @param  list<string>  $authors
     * @return list<array<string, mixed>>
     */
    private function deletionFilters(array $authors, ?int $since): array
    {
        return array_map(
            fn (array $chunk): array => $this->bounded(['kinds' => [NostrRsvpFold::KIND_DELETION], 'authors' => $chunk], $since),
            array_chunk($authors, self::AUTHORS_PER_FILTER),
        );
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<string, mixed>
     */
    private function bounded(array $filter, ?int $since): array
    {
        if ($since !== null) {
            $filter['since'] = $since;
        }

        $filter['limit'] = self::FILTER_LIMIT;

        return $filter;
    }

    /**
     * A filter that returned `limit` events may have been cut short by the relay. The
     * run still counts what it got — newest first, so a cut drops the oldest — but the
     * operator has to know, because the missing tail is only recovered by a full pass.
     *
     * @param  list<array<string, mixed>>  $events
     * @param  list<string>  $coordinates
     */
    private function warnIfTruncated(string $relayUrl, array $events, array $coordinates): void
    {
        $perCoordinate = [];

        foreach ($events as $event) {
            foreach ((array) ($event['tags'] ?? []) as $tag) {
                if (is_array($tag) && ($tag[0] ?? null) === 'a' && is_string($tag[1] ?? null)) {
                    $perCoordinate[$tag[1]] = ($perCoordinate[$tag[1]] ?? 0) + 1;
                }
            }
        }

        foreach (array_chunk($coordinates, self::COORDINATES_PER_FILTER) as $chunk) {
            $count = array_sum(array_intersect_key($perCoordinate, array_flip($chunk)));

            if ($count >= self::FILTER_LIMIT) {
                Log::warning('nostr:ingest-rsvps: a filter reached its limit, older RSVPs may be missing', [
                    'relay' => $relayUrl,
                    'events' => $count,
                ]);
                $this->warn("{$relayUrl}: a filter returned {$count} events (limit ".self::FILTER_LIMIT.')');
            }
        }
    }

    /**
     * The meetup event behind each referenced coordinate, including events outside the
     * scope — so that an RSVP to a cancelled or closed event is dropped with its REAL
     * reason instead of as "unknown".
     *
     * @param  list<string>  $coordinates
     * @param  list<string>  $publisherPubkeys
     * @return array<string, NostrRsvpTarget>
     */
    private function targetsFor(array $coordinates, array $publisherPubkeys): array
    {
        $targets = [];
        $windowStart = now()->subHours(self::PAST_GRACE_HOURS);

        // The lookup runs over the PRIMARY KEY, not over `nostr_coordinate` (finding F2).
        // The `d` tag of a portal event IS its id ("meetup-event-<id>"), so a coordinate
        // that cannot be parsed into an id belongs to nobody here and is dropped before
        // any query; the stored coordinate is compared afterwards in PHP, so the check
        // stays exact. `nostr_coordinate` is an unindexed TEXT column, and a `whereIn`
        // over 500 stranger-chosen strings against it was a full scan per run.
        $idsByCoordinate = [];

        foreach ($coordinates as $coordinate) {
            $dTag = explode(':', $coordinate, 3)[2] ?? '';

            if (preg_match('/^meetup-event-(\d+)$/', $dTag, $matches) === 1) {
                $idsByCoordinate[$coordinate] = (int) $matches[1];
            }
        }

        foreach (array_chunk(array_unique(array_values($idsByCoordinate)), 500) as $chunk) {
            MeetupEvent::query()
                ->with('meetup:id,rsvp_enabled,attendees_public')
                ->whereIn('id', $chunk)
                ->get(['id', 'meetup_id', 'start', 'cancelled_at', 'nostr_coordinate'])
                ->each(function (MeetupEvent $event) use (&$targets, $publisherPubkeys, $windowStart): void {
                    if (! in_array($event->nostr_coordinate, $this->expectedCoordinates($event, $publisherPubkeys), true)) {
                        return;
                    }

                    $targets[$event->nostr_coordinate] = new NostrRsvpTarget(
                        meetupEventId: $event->id,
                        cancelled: $event->isCancelled(),
                        rsvpEnabled: (bool) $event->meetup?->rsvp_enabled,
                        attendeesPublic: (bool) $event->meetup?->attendees_public,
                        inWindow: $event->start->greaterThanOrEqualTo($windowStart),
                    );
                });
        }

        return $targets;
    }

    /**
     * @param  list<int>  $meetupEventIds
     * @return array<string, array{nostr_event_id: string, d_tag: string, rsvp_created_at: int}>
     */
    private function storedRows(array $meetupEventIds): array
    {
        $stored = [];

        foreach (array_chunk($meetupEventIds, 500) as $chunk) {
            MeetupEventNostrRsvp::query()
                ->whereIn('meetup_event_id', $chunk)
                ->get(['meetup_event_id', 'pubkey', 'nostr_event_id', 'd_tag', 'rsvp_created_at'])
                ->each(function (MeetupEventNostrRsvp $row) use (&$stored): void {
                    $stored[$row->meetup_event_id.':'.$row->pubkey] = [
                        'nostr_event_id' => $row->nostr_event_id,
                        'd_tag' => $row->d_tag,
                        'rsvp_created_at' => $row->rsvp_created_at,
                    ];
                });
        }

        return $stored;
    }

    /**
     * Points every stored RSVP at the account whose `users.nostr` is its npub — the same
     * exact match the Nostr login uses (NostrLogin::findOrCreateUser). `users.nostr` has
     * no unique index; of several accounts with one npub the oldest is taken, which is
     * the row an unordered `first()` returns on the portal's databases.
     *
     * Written through the base query builder so `updated_at` does not move: it records
     * when the portal first saw the RSVP (see MeetupEventAttendance::nostrAnsweredAt).
     */
    private function relinkUsers(): int
    {
        $links = MeetupEventNostrRsvp::query()
            ->distinct()
            ->get(['pubkey', 'user_id'])
            ->groupBy('pubkey');

        $userIdByPubkey = $this->userIdsFor($links->keys()->all());
        $relinked = 0;

        foreach ($links as $pubkey => $rows) {
            $userId = $userIdByPubkey[$pubkey] ?? null;

            if ($rows->every(fn (MeetupEventNostrRsvp $row): bool => $row->user_id === $userId)) {
                continue;
            }

            $relinked += MeetupEventNostrRsvp::query()
                ->where('pubkey', $pubkey)
                ->toBase()
                ->update(['user_id' => $userId]);
        }

        return $relinked;
    }

    /**
     * pubkey => account id, for the keys given. Exact npub match, as the Nostr login uses
     * it; of several accounts carrying one npub the oldest wins.
     *
     * @param  list<string>  $pubkeys
     * @return array<string, int>
     */
    private function userIdsFor(array $pubkeys): array
    {
        $key = new Key;
        $npubs = [];

        foreach (array_unique($pubkeys) as $pubkey) {
            $npubs[$pubkey] = $key->convertPublicKeyToBech32($pubkey);
        }

        $userIdByNpub = [];

        foreach (array_chunk(array_values($npubs), 500) as $chunk) {
            User::query()
                ->whereIn('nostr', $chunk)
                ->orderBy('id')
                ->get(['id', 'nostr'])
                ->each(function (User $user) use (&$userIdByNpub): void {
                    $userIdByNpub[$user->nostr] ??= $user->id;
                });
        }

        $userIdByPubkey = [];

        foreach ($npubs as $pubkey => $npub) {
            if (isset($userIdByNpub[$npub])) {
                $userIdByPubkey[$pubkey] = $userIdByNpub[$npub];
            }
        }

        return $userIdByPubkey;
    }

    private function cursorKey(string $relayUrl): string
    {
        return self::CURSOR_CACHE_PREFIX.sha1($relayUrl);
    }
}
