<?php

namespace App\Enums;

/**
 * RSVP status of a user for a meetup event. `None` represents the state that the
 * user is on neither of the two attendee lists (= declined or never RSVP'd) and
 * at the same time serves as the input value for withdrawing an RSVP.
 */
enum RsvpStatus: string
{
    case Attending = 'attending';
    case Maybe = 'maybe';
    case None = 'none';
}
