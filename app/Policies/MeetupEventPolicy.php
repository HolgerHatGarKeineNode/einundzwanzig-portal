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

    public function update(User $user, MeetupEvent $meetupEvent): bool
    {
        return $this->owns($user, $meetupEvent);
    }
}
