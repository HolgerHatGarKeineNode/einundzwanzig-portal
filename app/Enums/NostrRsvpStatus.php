<?php

namespace App\Enums;

/**
 * The attendance status of a NIP-52 calendar event RSVP (kind 31925), spelled exactly
 * as the `status` tag carries it on the wire.
 *
 * A separate enum from {@see RsvpStatus} on purpose: the two describe the same idea in
 * two vocabularies, and the wire vocabulary is not ours to rename. The mapping between
 * them lives in {@see self::toRsvpStatus()} and nowhere else.
 */
enum NostrRsvpStatus: string
{
    case Accepted = 'accepted';
    case Tentative = 'tentative';
    case Declined = 'declined';

    /**
     * What this answer means on the portal's own attendee lists: `declined` puts the
     * person on neither list, which is exactly what {@see RsvpStatus::None} means.
     */
    public function toRsvpStatus(): RsvpStatus
    {
        return match ($this) {
            self::Accepted => RsvpStatus::Attending,
            self::Tentative => RsvpStatus::Maybe,
            self::Declined => RsvpStatus::None,
        };
    }
}
