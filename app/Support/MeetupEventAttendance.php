<?php

namespace App\Support;

use App\Enums\NostrRsvpStatus;
use App\Enums\RsvpStatus;
use App\Models\MeetupEvent;
use App\Models\MeetupEventNostrRsvp;
use App\Models\MeetupEventRsvpTime;
use Illuminate\Support\Collection;

/**
 * Who attends a meetup event once Nostr RSVPs are counted (D12a) — merged at READ time,
 * never written back into the attendee JSON.
 *
 * ## The rule
 *
 * - A Nostr RSVP whose key is LINKED to a portal account (`users.nostr` is its npub)
 *   is that user's answer, on the same lists and under the same name as a portal answer.
 *   Of the user's two answers the newer one wins; the older one is ignored entirely.
 * - An UNLINKED Nostr RSVP is counted apart, never named: it is "+N via Nostr", because
 *   a key is free to create and the portal cannot say who is behind it (plan risk R10).
 *
 * ## Nothing unlinked is ever hydrated
 *
 * The unlinked RSVPs are exactly the part of this table a stranger can grow at will, so
 * they are only ever COUNTED, in SQL: either as an aggregate the query already selected
 * ({@see MeetupEvent::scopeWithAttendanceCounts()}) or as one `count()` per event. Only
 * the LINKED rows are loaded as models, and their number is bounded by the accounts that
 * linked a key. Measured before this split, on one event with 20 000 unlinked rows:
 * 36 MB and 332 ms per request; the numbers after it are in the rework report.
 *
 * ## "Newer"
 *
 * The portal side is {@see MeetupEventRsvpTime::$answered_at}. Since the backfill in
 * `2026_09_18_090000_backfill_meetup_event_rsvp_times`, every portal answer visible on a
 * list has such a row, so a MISSING row means "this account never answered in the
 * portal" — the Nostr answer then stands unopposed. The one state that can still arise
 * (a list entry whose row was removed, e.g. by an account merge) is resolved FOR the
 * portal: an answer whose time is unknown is not beaten by a Nostr answer of unknown
 * relative age. On an exact tie the portal answer wins, being the one the portal
 * recorded itself.
 *
 * The Nostr side is the RSVP's `created_at`, CLAMPED to when the portal first stored it
 * ({@see self::nostrAnsweredAt()}). `created_at` is chosen by the author and the ingest
 * accepts up to 15 minutes of clock skew into the future; without the clamp, a phone
 * running a few minutes fast would make every portal answer given in those minutes lose
 * — the user presses "Absagen" and stays on the list.
 */
final class MeetupEventAttendance
{
    /**
     * The answer a newer, linked Nostr RSVP gives on behalf of a user.
     *
     * @var array<int, RsvpStatus>
     */
    private array $overrides = [];

    /**
     * The users who hold an entry on one of the portal lists — the answers that must not
     * be overruled by a Nostr RSVP of unknown relative age.
     *
     * @var array<int, true>
     */
    private array $onPortalList = [];

    private function __construct(private readonly MeetupEvent $meetupEvent)
    {
        foreach ([$meetupEvent->attendees, $meetupEvent->might_attendees] as $list) {
            foreach ((array) ($list ?? []) as $entry) {
                $userId = self::userIdOf((string) $entry);

                if ($userId !== null) {
                    $this->onPortalList[$userId] = true;
                }
            }
        }

        $answeredAt = $meetupEvent->relationLoaded('rsvpTimes')
            ? $meetupEvent->rsvpTimes
                ->mapWithKeys(fn (MeetupEventRsvpTime $time): array => [$time->user_id => $time->answered_at->getTimestamp()])
                ->all()
            : [];

        foreach ($meetupEvent->linkedNostrRsvps as $rsvp) {
            $portalAnsweredAt = $answeredAt[$rsvp->user_id] ?? null;

            if ($portalAnsweredAt === null && isset($this->onPortalList[$rsvp->user_id])) {
                continue;
            }

            if ($portalAnsweredAt !== null && $portalAnsweredAt >= self::nostrAnsweredAt($rsvp)) {
                continue;
            }

            $this->overrides[$rsvp->user_id] = $rsvp->status->toRsvpStatus();
        }
    }

    /**
     * Loads the linked RSVPs, and the portal answer times only when there is a linked
     * RSVP to compare them with. A query that selected the aggregates of
     * {@see MeetupEvent::scopeWithAttendanceCounts()} skips both loads when it already
     * knows there is no linked RSVP.
     */
    public static function for(MeetupEvent $meetupEvent): self
    {
        if (! $meetupEvent->relationLoaded('linkedNostrRsvps') && $meetupEvent->getAttribute('linked_nostr_rsvps_exists') === false) {
            $meetupEvent->setRelation('linkedNostrRsvps', $meetupEvent->linkedNostrRsvps()->getRelated()->newCollection());
        }

        $meetupEvent->loadMissing('linkedNostrRsvps');

        if ($meetupEvent->linkedNostrRsvps->isNotEmpty()) {
            $meetupEvent->loadMissing('rsvpTimes');
        }

        return new self($meetupEvent);
    }

    /**
     * When a Nostr answer counts as given: its own `created_at`, but never later than the
     * moment the portal stored it (`updated_at` moves only when the winning RSVP changes).
     */
    public static function nostrAnsweredAt(MeetupEventNostrRsvp $rsvp): int
    {
        $storedAt = $rsvp->updated_at?->getTimestamp();

        return $storedAt === null ? $rsvp->rsvp_created_at : min($rsvp->rsvp_created_at, $storedAt);
    }

    public function statusFor(int $userId): RsvpStatus
    {
        if (isset($this->overrides[$userId])) {
            return $this->overrides[$userId];
        }

        if ($this->portalEntries($this->meetupEvent->attendees)->contains(fn (string $entry): bool => self::userIdOf($entry) === $userId)) {
            return RsvpStatus::Attending;
        }

        if ($this->portalEntries($this->meetupEvent->might_attendees)->contains(fn (string $entry): bool => self::userIdOf($entry) === $userId)) {
            return RsvpStatus::Maybe;
        }

        return RsvpStatus::None;
    }

    public function attendeesCount(): int
    {
        return $this->portalEntries($this->meetupEvent->attendees)->count() + $this->overrideCount(RsvpStatus::Attending);
    }

    public function mightAttendeesCount(): int
    {
        return $this->portalEntries($this->meetupEvent->might_attendees)->count() + $this->overrideCount(RsvpStatus::Maybe);
    }

    /**
     * The attending list in the stored `id_<userId>|<name>` / `anon_…|<name>` format,
     * with linked Nostr answers merged in under the account's current name.
     *
     * @return list<string>
     */
    public function attendeeEntries(): array
    {
        return $this->entries($this->meetupEvent->attendees, RsvpStatus::Attending);
    }

    /**
     * @return list<string>
     */
    public function mightAttendeeEntries(): array
    {
        return $this->entries($this->meetupEvent->might_attendees, RsvpStatus::Maybe);
    }

    /**
     * Nostr RSVPs of keys that belong to no portal account ("+N via Nostr").
     *
     * Read from the aggregate the query selected, or counted in SQL — never by walking
     * rows, whatever their number.
     */
    public function unlinkedNostrCount(NostrRsvpStatus $status): int
    {
        $selected = $this->meetupEvent->getAttribute("nostr_unlinked_{$status->value}_count");

        if ($selected !== null) {
            return (int) $selected;
        }

        return $this->meetupEvent->nostrRsvps()
            ->whereNull('user_id')
            ->where('status', $status->value)
            ->count();
    }

    /**
     * @param  array<int, string>|null  $stored
     * @return list<string>
     */
    private function entries(?array $stored, RsvpStatus $status): array
    {
        $entries = $this->portalEntries($stored)->all();

        if (in_array($status, $this->overrides, true)) {
            $this->meetupEvent->loadMissing('linkedNostrRsvps.user:id,name');

            foreach ($this->meetupEvent->linkedNostrRsvps as $rsvp) {
                if (($this->overrides[$rsvp->user_id] ?? null) === $status) {
                    $entries[] = 'id_'.$rsvp->user_id.'|'.($rsvp->user->name ?? '');
                }
            }
        }

        return $entries;
    }

    /**
     * The stored entries, minus those of users whose newer answer came from Nostr.
     *
     * @param  array<int, string>|null  $stored
     * @return Collection<int, string>
     */
    private function portalEntries(?array $stored): Collection
    {
        return collect($stored ?? [])
            ->reject(function ($entry): bool {
                $userId = self::userIdOf((string) $entry);

                return $userId !== null && isset($this->overrides[$userId]);
            })
            ->values();
    }

    private function overrideCount(RsvpStatus $status): int
    {
        return count(array_filter($this->overrides, fn (RsvpStatus $override): bool => $override === $status));
    }

    /**
     * The user id of an `id_<userId>|<name>` entry, or null for a guest's `anon_…` entry.
     */
    private static function userIdOf(string $entry): ?int
    {
        return preg_match('/^id_(\d+)\|/', $entry, $matches) === 1 ? (int) $matches[1] : null;
    }
}
