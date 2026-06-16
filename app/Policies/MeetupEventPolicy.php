<?php

namespace App\Policies;

use App\Models\MeetupEvent;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

class MeetupEventPolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MeetupEvent $meetupEvent): bool
    {
        return $this->owns($user, $meetupEvent);
    }

    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Termin bearbeiten: der Ersteller des Termins ODER ein Leader des
     * zugehörigen Meetups (bzw. Super-Admin). Damit dürfen Meetup-Leader die
     * Termine ihres Meetups pflegen, auch wenn sie sie nicht selbst angelegt
     * haben — analog zur Stammdaten-Bearbeitung (MeetupPolicy::update).
     */
    public function update(User $user, MeetupEvent $meetupEvent): bool
    {
        return $this->owns($user, $meetupEvent)
            || ($meetupEvent->meetup !== null && $meetupEvent->meetup->isLeader($user));
    }

    /**
     * Termin löschen: gleiche Regel wie das Bearbeiten.
     */
    public function delete(User $user, MeetupEvent $meetupEvent): bool
    {
        return $this->update($user, $meetupEvent);
    }
}
