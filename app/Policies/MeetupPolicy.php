<?php

namespace App\Policies;

use App\Models\Meetup;
use App\Models\User;
use App\Policies\Concerns\ChecksCreatorOwnership;

class MeetupPolicy
{
    use ChecksCreatorOwnership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Meetup $meetup): bool
    {
        return $this->owns($user, $meetup);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Meetup $meetup): bool
    {
        return $this->owns($user, $meetup);
    }
}
