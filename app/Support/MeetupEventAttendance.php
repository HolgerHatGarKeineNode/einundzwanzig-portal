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
 * ## "Newer"
 *
 * The portal side is {@see MeetupEventRsvpTime::$answered_at}; a portal
 * answer without a row predates that table and counts as older than any Nostr answer.
 * On an exact tie the portal answer wins, being the one the portal recorded itself.
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

    private function __construct(private readonly MeetupEvent $meetupEvent)
    {
        $answeredAt = $meetupEvent->relationLoaded('rsvpTimes')
            ? $meetupEvent->rsvpTimes
                ->mapWithKeys(fn (MeetupEventRsvpTime $time): array => [$time->user_id => $time->answered_at->getTimestamp()])
                ->all()
            : [];

        foreach ($meetupEvent->nostrRsvps as $rsvp) {
            if ($rsvp->user_id === null) {
                continue;
            }

            $portalAnsweredAt = $answeredAt[$rsvp->user_id] ?? null;

            if ($portalAnsweredAt !== null && $portalAnsweredAt >= self::nostrAnsweredAt($rsvp)) {
                continue;
            }

            $this->overrides[$rsvp->user_id] = $rsvp->status->toRsvpStatus();
        }
    }

    /**
     * The portal answer times only matter for linked Nostr RSVPs, so they are only loaded
     * when there is one. Measured 2026-09-17 on `GET /api/meetups` with 20 meetups, each
     * with one upcoming event and five of them with a linked Nostr RSVP: without these two
     * shortcuts the endpoint ran 51 queries (26 before this feature), with them 36 — the
     * ten extra are the two loads of each of the five events that have an RSVP.
     */
    public static function for(MeetupEvent $meetupEvent): self
    {
        // A query that selected `withExists('nostrRsvps')` already knows there is nothing
        // to load; every other caller loads the rows once.
        if (! $meetupEvent->relationLoaded('nostrRsvps') && $meetupEvent->getAttribute('nostr_rsvps_exists') === false) {
            $meetupEvent->setRelation('nostrRsvps', $meetupEvent->nostrRsvps()->getRelated()->newCollection());
        }

        $meetupEvent->loadMissing('nostrRsvps');

        if ($meetupEvent->nostrRsvps->contains(fn (MeetupEventNostrRsvp $rsvp): bool => $rsvp->user_id !== null)) {
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
     */
    public function unlinkedNostrCount(NostrRsvpStatus $status): int
    {
        return $this->meetupEvent->nostrRsvps
            ->filter(fn (MeetupEventNostrRsvp $rsvp): bool => $rsvp->user_id === null && $rsvp->status === $status)
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
            $this->meetupEvent->loadMissing('nostrRsvps.user:id,name');

            foreach ($this->meetupEvent->nostrRsvps as $rsvp) {
                if ($rsvp->user_id !== null && ($this->overrides[$rsvp->user_id] ?? null) === $status) {
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
