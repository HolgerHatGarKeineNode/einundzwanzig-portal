<?php

namespace App\Support;

/**
 * The portal-side facts an RSVP is checked against: the meetup event behind one
 * published kind 31923 coordinate, reduced to the flags the fold decides on.
 *
 * Plain values rather than a model, so {@see NostrRsvpFold} stays free of the database
 * and its rules can be tested as pure functions.
 */
final readonly class NostrRsvpTarget
{
    public function __construct(
        public int $meetupEventId,
        public bool $cancelled,
        public bool $rsvpEnabled,
        public bool $attendeesPublic,
        public bool $inWindow,
    ) {}
}
